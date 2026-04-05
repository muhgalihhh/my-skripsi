"""
Scraper Repository UNSOED (S1 Teknik Informatika) - simple run mode.

Cara pakai:
  Dari folder analysis:
    ../.venv-1/bin/python scraping_unsoed.py

Tanpa argumen. Semua konfigurasi ada di bagian CONFIG di bawah.
"""

from __future__ import annotations

import json
import logging
import os
import re
import time
from io import BytesIO
from typing import Any, Dict, List, Optional
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup


# ===================== CONFIG =====================
BASE_URL = "https://repository.unsoed.ac.id"
SEARCH_URL = (
    "https://repository.unsoed.ac.id/cgi/search/archive/advanced"
    "?order=-date%2Fcreators_name%2Ftitle"
    "&_action_search=1"
    "&exp=0%7C1%7C-date%2Fcreators_name%2Ftitle%7Carchive"
    "%7C-%7Cdivisions%3Adivisions%3AANY%3AEQ%3Atek_info"
    "%7C-%7Ceprint_status%3Aeprint_status%3AANY%3AEQ%3Aarchive"
    "%7Cmetadata_visibility%3Ametadata_visibility%3AANY%3AEQ%3Ashow"
    "&screen=Search"
)

ITEMS_PER_PAGE = 20
REQUEST_TIMEOUT = 30
REQUEST_DELAY_SECONDS = 1.0
MAX_RETRIES = 3

# Set None untuk scrape semua halaman.
MAX_PAGES: Optional[int] = None

# Filter tahun (None = tidak difilter)
START_YEAR: Optional[int] = None
END_YEAR: Optional[int] = None

# Resume dari JSON existing
RESUME = True

# Ambil kesimpulan dari PDF Bab V/VI
EXTRACT_CONCLUSION = True

OUTPUT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data_user")
OUTPUT_JSON = os.path.join(OUTPUT_DIR, "skripsi_unsoed_scraped.json")
OUTPUT_CSV = os.path.join(OUTPUT_DIR, "skripsi_unsoed_scraped.csv")


# ===================== LOGGING =====================
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger(__name__)


# ===================== HTTP SESSION =====================
session = requests.Session()
session.headers.update(
    {
        "User-Agent": (
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
            "AppleWebKit/537.36 (KHTML, like Gecko) "
            "Chrome/120.0.0.0 Safari/537.36"
        ),
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
    }
)


PDF_ENGINE = None
try:
    import pdfplumber  # type: ignore

    PDF_ENGINE = "pdfplumber"
except Exception:
    try:
        import PyPDF2  # type: ignore

        PDF_ENGINE = "pypdf2"
    except Exception:
        PDF_ENGINE = None


def fetch_page(url: str) -> Optional[requests.Response]:
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            resp = session.get(url, timeout=REQUEST_TIMEOUT)
            resp.raise_for_status()
            return resp
        except requests.RequestException as exc:
            logger.warning("Request gagal (%s/%s): %s", attempt, MAX_RETRIES, exc)
            if attempt < MAX_RETRIES:
                time.sleep(REQUEST_DELAY_SECONDS * 2)
    logger.error("Gagal mengambil halaman: %s", url)
    return None


def safe_int(val: Any) -> Optional[int]:
    try:
        if val is None:
            return None
        return int(str(val).strip())
    except Exception:
        return None


def in_year_range(year: Any) -> bool:
    y = safe_int(year)
    if y is None:
        return True
    if START_YEAR is not None and y < START_YEAR:
        return False
    if END_YEAR is not None and y > END_YEAR:
        return False
    return True


def normalize_space(text: Any) -> str:
    return re.sub(r"\s+", " ", str(text or "")).strip()


def normalize_url(url: str) -> str:
    u = (url or "").strip()
    if u.startswith("/"):
        u = urljoin(BASE_URL, u)
    return u.rstrip("/")


def classify_document(text: str, href: str) -> Optional[str]:
    combined = f"{text} {href}".lower()

    if re.search(r"\bbab[\s\-_]*vi\b|\bbab[\s\-_]*6\b", combined):
        return "Bab VI"
    if re.search(r"\bbab[\s\-_]*v\b|\bbab[\s\-_]*5\b", combined):
        return "Bab V"

    if "kesimpulan" in combined and "abstrak" not in combined:
        return "Bab V"
    if any(k in combined for k in ["daftarpustaka", "daftar pustaka", "references"]):
        return "Daftar Pustaka"
    if any(k in combined for k in ["abstrak", "abstract"]):
        return "Abstrak"
    if "cover" in combined:
        return "Cover"
    if any(k in combined for k in ["legalitas", "legal"]):
        return "Legalitas"
    if "lampiran" in combined:
        return "Lampiran"

    return None


def get_total_results(soup: BeautifulSoup) -> int:
    controls = soup.find("div", class_="ep_search_controls")
    if not controls:
        return 0
    nums = controls.find_all("span", class_="ep_search_number")
    if len(nums) < 3:
        return 0
    try:
        return int(nums[2].get_text(strip=True))
    except Exception:
        return 0


def parse_listing_page(soup: BeautifulSoup) -> List[Dict[str, str]]:
    results: List[Dict[str, str]] = []
    container = soup.find("div", class_="ep_search_results")
    if not container:
        return results

    rows = container.find_all("tr", class_="ep_search_result")
    for row in rows:
        tds = row.find_all("td")
        if len(tds) < 2:
            continue

        info_td = tds[1]
        text = info_td.get_text(" ", strip=True)

        author_span = info_td.find("span", class_="person_name")
        author = normalize_space(author_span.get_text(strip=True) if author_span else "")

        year_match = re.search(r"\((\d{4})\)", text)
        year_listing = year_match.group(1) if year_match else ""

        link_tag = info_td.find("a", href=True)
        if not link_tag:
            continue

        detail_url = normalize_url(link_tag.get("href", ""))
        if not detail_url:
            continue

        em = link_tag.find("em")
        title_listing = normalize_space(em.get_text(strip=True) if em else link_tag.get_text(strip=True))

        if not in_year_range(year_listing):
            continue

        results.append(
            {
                "detail_url": detail_url,
                "judul_listing": title_listing,
                "penulis_listing": author,
                "tahun_listing": year_listing,
            }
        )

    return results


def parse_detail_page(url: str) -> Optional[Dict[str, Any]]:
    resp = fetch_page(url)
    if not resp:
        return None

    soup = BeautifulSoup(resp.content, "html.parser")

    # Title
    title = ""
    for h1 in soup.find_all("h1"):
        t = normalize_space(h1.get_text(strip=True))
        if t and t not in ["Welcome to", "Repository Universitas Jenderal Soedirman"]:
            title = t
            break

    # Abstract
    abstract = ""
    meta_abstract = soup.find("meta", attrs={"name": "eprints.abstract"})
    if meta_abstract:
        abstract = normalize_space(meta_abstract.get("content", ""))

    # Metadata table
    metadata: Dict[str, str] = {}
    tables = soup.find_all("table")
    for table in tables:
        for row in table.find_all("tr"):
            cells = row.find_all(["th", "td"])
            if len(cells) >= 2:
                key = normalize_space(cells[0].get_text(strip=True)).rstrip(":")
                val = normalize_space(cells[1].get_text(" ", strip=True))
                if key:
                    metadata[key] = val

    # Author & year
    author = normalize_space(metadata.get("Depositing User", ""))
    year = ""

    citation = soup.find("div", class_="ep_summary_content_main")
    if citation:
        ctext = normalize_space(citation.get_text(strip=True))
        m_author = re.match(r"^(.+?)\s*\(\d{4}\)", ctext)
        m_year = re.search(r"\((\d{4})\)", ctext)
        if m_author:
            author = normalize_space(m_author.group(1))
        if m_year:
            year = m_year.group(1)

    if not year:
        y = re.search(r"(\d{4})", metadata.get("Date Deposited", ""))
        if y:
            year = y.group(1)

    # PDF links
    pdf_links: Dict[str, str] = {}
    doc_table = None
    for table in tables:
        if table.find("a", class_="ep_document_link"):
            doc_table = table
            break

    if doc_table:
        for row in doc_table.find_all("tr"):
            for cell in row.find_all("td"):
                cell_text = normalize_space(cell.get_text(" ", strip=True))
                for a in cell.find_all("a", href=True):
                    href = a.get("href", "")
                    if ".pdf" not in href.lower():
                        continue
                    doc_type = classify_document(cell_text, href)
                    if doc_type:
                        pdf_links[doc_type] = normalize_url(href)
    else:
        for a in soup.find_all("a", href=True):
            href = a.get("href", "")
            if ".pdf" not in href.lower():
                continue
            label = normalize_space(a.get_text(strip=True))
            doc_type = classify_document(label, href)
            if doc_type:
                pdf_links[doc_type] = normalize_url(href)

    return {
        "Judul": title,
        "Penulis": author,
        "Tahun": year,
        "Tanggal Deposit": normalize_space(metadata.get("Date Deposited", "")),
        "Subjects": normalize_space(metadata.get("Subjects", "")),
        "Divisions": normalize_space(metadata.get("Divisions", "")),
        "Kata Kunci": normalize_space(
            metadata.get("Uncontrolled Keywords", metadata.get("Keywords", ""))
        ),
        "Abstrak": abstract,
        "Tipe": normalize_space(metadata.get("Item Type", metadata.get("Type", "")),),
        "ID Code": normalize_space(metadata.get("ID Code", "")),
        "URI": normalize_space(metadata.get("URI", "")),
        "Dokumen_PDF": pdf_links,
        "URL": normalize_url(url),
        "Kesimpulan": "",
        "Sumber Kesimpulan": "",
    }


def extract_text_from_pdf_url(pdf_url: str) -> str:
    if not PDF_ENGINE:
        return ""

    try:
        resp = session.get(pdf_url, timeout=REQUEST_TIMEOUT, allow_redirects=True)
        resp.raise_for_status()
        content_type = (resp.headers.get("Content-Type") or "").lower()
        if "pdf" not in content_type and ".pdf" not in pdf_url.lower():
            return ""

        pdf_bytes = BytesIO(resp.content)

        if PDF_ENGINE == "pdfplumber":
            import pdfplumber  # type: ignore

            texts: List[str] = []
            with pdfplumber.open(pdf_bytes) as pdf:
                for page in pdf.pages:
                    t = page.extract_text() or ""
                    if t.strip():
                        texts.append(t)
            return "\n".join(texts).strip()

        if PDF_ENGINE == "pypdf2":
            import PyPDF2  # type: ignore

            reader = PyPDF2.PdfReader(pdf_bytes)
            texts = []
            for page in reader.pages:
                t = page.extract_text() or ""
                if t.strip():
                    texts.append(t)
            return "\n".join(texts).strip()

    except Exception as exc:
        logger.debug("Gagal ekstrak PDF %s: %s", pdf_url, exc)

    return ""


def extract_conclusion_from_text(text: str) -> str:
    if not text:
        return ""

    patterns = [
        r"(?:5\.1\.?\s*)?Kesimpulan\s*\n(.*?)(?:5\.2\.?\s*Saran|DAFTAR PUSTAKA|SARAN|$)",
        r"(?:6\.1\.?\s*)?Kesimpulan\s*\n(.*?)(?:6\.2\.?\s*Saran|DAFTAR PUSTAKA|SARAN|$)",
        r"KESIMPULAN\s*\n(.*?)(?:SARAN|DAFTAR PUSTAKA|$)",
        r"BAB\s*(?:V|VI)\s*\n.*?Kesimpulan\s*\n(.*?)(?:Saran|DAFTAR PUSTAKA|$)",
    ]

    for pattern in patterns:
        m = re.search(pattern, text, flags=re.DOTALL | re.IGNORECASE)
        if m:
            return normalize_space(m.group(1))

    return ""


def enrich_conclusion_from_pdf(item: Dict[str, Any]) -> None:
    docs = item.get("Dokumen_PDF") or {}
    if not isinstance(docs, dict):
        return

    for key in ["Bab V", "Bab VI"]:
        pdf_url = docs.get(key)
        if not pdf_url:
            continue
        logger.info("  Coba ekstrak kesimpulan dari %s", key)
        text = extract_text_from_pdf_url(pdf_url)
        conc = extract_conclusion_from_text(text)
        if conc:
            item["Kesimpulan"] = conc
            item["Sumber Kesimpulan"] = f"{key} (PDF)"
            return


def save_json(data: List[Dict[str, Any]]) -> None:
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    with open(OUTPUT_JSON, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def save_csv(data: List[Dict[str, Any]]) -> None:
    import pandas as pd

    rows = []
    for d in data:
        rows.append(
            {
                "title": d.get("Judul", ""),
                "abstract": d.get("Abstrak", ""),
                "year": safe_int(d.get("Tahun")),
                "author": d.get("Penulis", ""),
                "keywords": d.get("Kata Kunci", ""),
                "subjects": d.get("Subjects", ""),
                "divisions": d.get("Divisions", ""),
                "conclusion": d.get("Kesimpulan", ""),
                "conclusion_source": d.get("Sumber Kesimpulan", ""),
                "url": d.get("URL", ""),
                "pdf_links": json.dumps(d.get("Dokumen_PDF", {}), ensure_ascii=False),
            }
        )

    df = pd.DataFrame(rows)
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    df.to_csv(OUTPUT_CSV, index=False, encoding="utf-8")


def run_scrape() -> None:
    logger.info("Mulai scraping UNSOED Informatika...")

    first_url = f"{SEARCH_URL}&search_offset=0"
    first_resp = fetch_page(first_url)
    if not first_resp:
        raise RuntimeError("Gagal mengambil halaman pertama")

    first_soup = BeautifulSoup(first_resp.content, "html.parser")
    total_results = get_total_results(first_soup)
    if total_results == 0:
        raise RuntimeError("Tidak ada hasil ditemukan")

    total_pages = (total_results + ITEMS_PER_PAGE - 1) // ITEMS_PER_PAGE
    if MAX_PAGES is not None:
        total_pages = min(total_pages, MAX_PAGES)

    logger.info("Total hasil: %s | total halaman diproses: %s", total_results, total_pages)

    listings: List[Dict[str, str]] = []
    for page_idx in range(total_pages):
        if page_idx == 0:
            soup = first_soup
        else:
            time.sleep(REQUEST_DELAY_SECONDS)
            offset = page_idx * ITEMS_PER_PAGE
            resp = fetch_page(f"{SEARCH_URL}&search_offset={offset}")
            if not resp:
                continue
            soup = BeautifulSoup(resp.content, "html.parser")

        page_items = parse_listing_page(soup)
        listings.extend(page_items)
        logger.info("Halaman %s/%s: +%s item (total %s)", page_idx + 1, total_pages, len(page_items), len(listings))

    existing: Dict[str, Dict[str, Any]] = {}
    data: List[Dict[str, Any]] = []

    if RESUME and os.path.exists(OUTPUT_JSON):
        try:
            with open(OUTPUT_JSON, "r", encoding="utf-8") as f:
                old_data = json.load(f)
            for item in old_data:
                existing[normalize_url(item.get("URL", ""))] = item
            data = old_data
            logger.info("Resume aktif: %s data existing ditemukan", len(data))
        except Exception:
            existing = {}
            data = []

    new_count = 0
    backfill_count = 0

    for idx, info in enumerate(listings, start=1):
        detail_url = normalize_url(info.get("detail_url", ""))
        if not detail_url:
            continue

        old_item = existing.get(detail_url)
        if old_item is not None:
            if EXTRACT_CONCLUSION and not normalize_space(old_item.get("Kesimpulan", "")):
                enrich_conclusion_from_pdf(old_item)
                if normalize_space(old_item.get("Kesimpulan", "")):
                    backfill_count += 1
            logger.info("[%s/%s] skip existing", idx, len(listings))
            continue

        logger.info("[%s/%s] scrape detail: %s", idx, len(listings), detail_url)
        time.sleep(REQUEST_DELAY_SECONDS)

        detail = parse_detail_page(detail_url)
        if not detail:
            continue

        if not detail.get("Judul"):
            detail["Judul"] = info.get("judul_listing", "")
        if not detail.get("Penulis"):
            detail["Penulis"] = info.get("penulis_listing", "")
        if not detail.get("Tahun"):
            detail["Tahun"] = info.get("tahun_listing", "")

        if not in_year_range(detail.get("Tahun")):
            continue

        if EXTRACT_CONCLUSION:
            enrich_conclusion_from_pdf(detail)

        data.append(detail)
        existing[detail_url] = detail
        new_count += 1

        if new_count % 10 == 0:
            save_json(data)
            save_csv(data)
            logger.info("Progress simpan: total %s", len(data))

    save_json(data)
    save_csv(data)

    with_conclusion = sum(1 for d in data if normalize_space(d.get("Kesimpulan", "")))
    logger.info("Selesai.")
    logger.info("Data total: %s", len(data))
    logger.info("Data baru: %s", new_count)
    logger.info("Backfill kesimpulan existing: %s", backfill_count)
    logger.info("Dengan kesimpulan: %s", with_conclusion)
    logger.info("JSON: %s", OUTPUT_JSON)
    logger.info("CSV : %s", OUTPUT_CSV)


if __name__ == "__main__":
    start = time.time()
    try:
        run_scrape()
    finally:
        logger.info("Durasi: %.2f menit", (time.time() - start) / 60.0)
