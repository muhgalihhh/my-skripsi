"""
Scraping Service
Handles web scraping from UNSOED repository.
Based on analysis/scraping_unsoed.py — adapted for FastAPI microservice.
"""

import io
import re
import time
from typing import Any, Dict, List, Optional
from urllib.parse import urljoin

import requests
from app.core.config import scraping_settings
from bs4 import BeautifulSoup
from loguru import logger

# Constants
ITEMS_PER_PAGE = 20
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


class ScrapingService:
    """
    Service for scraping thesis data from UNSOED repository.
    Adapted from analysis/scraping_unsoed.py for use as a FastAPI microservice.
    """

    def __init__(self):
        self.base_url = scraping_settings.SCRAPING_BASE_URL
        self.delay = scraping_settings.SCRAPING_DELAY
        self.max_retries = scraping_settings.SCRAPING_MAX_RETRIES
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": (
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
        })

    def _normalize_space(self, text: Any) -> str:
        return re.sub(r"\s+", " ", str(text or "")).strip()

    def _normalize_url(self, url: str) -> str:
        cleaned = (url or "").strip()
        if cleaned.startswith("/"):
            cleaned = urljoin(self.base_url, cleaned)
        return cleaned.rstrip("/")

    def _safe_int(self, val: Any) -> Optional[int]:
        try:
            if val is None:
                return None
            return int(str(val).strip())
        except Exception:
            return None

    def _in_year_range(self, year: Any, start_year: Optional[int], end_year: Optional[int]) -> bool:
        y = self._safe_int(year)
        if y is None:
            return True
        if start_year is not None and y < start_year:
            return False
        if end_year is not None and y > end_year:
            return False
        return True

    def scrape(
        self,
        start_year: int = 2019,
        end_year: int = 2026,
        max_pages: Optional[int] = None,
        job=None,
    ) -> List[Dict]:
        """
        Scrape thesis data from UNSOED repository.

        Args:
            start_year: Start year filter
            end_year: End year filter
            max_pages: Max listing pages to crawl
            job: Optional ScrapingJob instance for progress tracking

        Returns:
            List of dicts, each representing a scraped document
        """
        logger.info(f"Starting scraping for years {start_year}-{end_year}")

        # Step 1: Get first page to know total results
        first_page_url = f"{SEARCH_URL}&search_offset=0"
        response = self._fetch_page(first_page_url)
        if not response:
            raise Exception("Failed to fetch first page from repository")

        soup = BeautifulSoup(response.content, "html.parser")
        total_results = self._get_total_results(soup)
        logger.info(f"Total results found: {total_results}")

        if total_results == 0:
            return []

        total_pages = (total_results + ITEMS_PER_PAGE - 1) // ITEMS_PER_PAGE
        if max_pages:
            total_pages = min(total_pages, max_pages)

        # Step 2: Collect all detail URLs from listing pages
        all_detail_urls = []
        all_basic_info = []

        for page_num in range(total_pages):
            # Check if job was cancelled
            if job and hasattr(job, 'status') and job.status.value == 'cancelled':
                logger.info("Job cancelled, stopping listing phase")
                return []

            offset = page_num * ITEMS_PER_PAGE
            logger.info(f"Listing page {page_num + 1}/{total_pages} (offset: {offset})")

            if job:
                job.update_listing_progress(page_num + 1, total_pages)

            if page_num == 0:
                page_soup = soup
            else:
                time.sleep(self.delay)
                page_url = f"{SEARCH_URL}&search_offset={offset}"
                response = self._fetch_page(page_url)
                if not response:
                    logger.warning(f"Failed to fetch page {page_num + 1}, skipping")
                    continue
                page_soup = BeautifulSoup(response.content, "html.parser")

            results_div = page_soup.find("div", class_="ep_search_results")
            if results_div:
                rows = results_div.find_all("tr", class_="ep_search_result")
                for row in rows:
                    basic_info = self._extract_basic_info(row)
                    if basic_info.get("detail_url") and self._in_year_range(
                        basic_info.get("tahun_listing"), start_year, end_year
                    ):
                        all_detail_urls.append(basic_info["detail_url"])
                        all_basic_info.append(basic_info)
                        # Track found URL for monitoring
                        if job:
                            job._add_found_url(
                                url=basic_info["detail_url"],
                                title=basic_info.get("judul_listing", ""),
                                author=basic_info.get("penulis_listing", ""),
                                year=basic_info.get("tahun_listing", ""),
                            )

        logger.info(f"Total detail URLs collected: {len(all_detail_urls)}")

        if job:
            job.total_detail_urls = len(all_detail_urls)

        # Step 3: Visit each detail page and extract data
        all_data = []
        for idx, detail_url in enumerate(all_detail_urls):
            # Check if job was cancelled
            if job and hasattr(job, 'status') and job.status.value == 'cancelled':
                logger.info("Job cancelled, stopping detail phase")
                break

            logger.info(f"[{idx + 1}/{len(all_detail_urls)}] Scraping detail: {detail_url}")

            if job:
                basic = all_basic_info[idx] if idx < len(all_basic_info) else {}
                job.update_progress(
                    scraped_count=idx + 1,
                    total=len(all_detail_urls),
                    current_step=f"Scraping detail {idx + 1}/{len(all_detail_urls)}",
                )

            time.sleep(self.delay)

            detail_data = self._parse_detail_page(detail_url)
            if not detail_data:
                if job:
                    job.skipped_count += 1
                    job._add_scraped_item(
                        title=all_basic_info[idx].get("judul_listing", "Unknown") if idx < len(all_basic_info) else "Unknown",
                        status="failed",
                    )
                continue

            # Fill from listing info if detail is missing
            basic = all_basic_info[idx] if idx < len(all_basic_info) else {}
            if not detail_data.get("Judul") and basic.get("judul_listing"):
                detail_data["Judul"] = basic["judul_listing"]
            if not detail_data.get("Tahun") and basic.get("tahun_listing"):
                detail_data["Tahun"] = basic["tahun_listing"]
            if not detail_data.get("Penulis") and basic.get("penulis_listing"):
                detail_data["Penulis"] = basic["penulis_listing"]

            # Track scraped item with actual parsed data
            if job:
                job._add_scraped_item(
                    title=detail_data.get("Judul", "Unknown"),
                    author=detail_data.get("Penulis", ""),
                    year=detail_data.get("Tahun", ""),
                    status="success",
                )

            # Try to extract conclusion from PDF (Bab V)
            detail_data["Kesimpulan"] = ""
            detail_data["Sumber Kesimpulan"] = ""
            pdf_docs = detail_data.get("Dokumen_PDF", {})
            for bab_key in ["Bab V", "Bab VI"]:
                if bab_key in pdf_docs:
                    text = self._try_extract_pdf_text(pdf_docs[bab_key])
                    if text:
                        detail_data["Kesimpulan"] = self._extract_conclusion(text)
                        detail_data["Sumber Kesimpulan"] = f"{bab_key} (PDF)"
                        break

            # Filter by year range
            try:
                doc_year = int(detail_data.get("Tahun", 0))
                if start_year <= doc_year <= end_year:
                    all_data.append(detail_data)
                else:
                    if job:
                        job.filtered_count += 1
            except (ValueError, TypeError):
                all_data.append(detail_data)

        logger.info(f"Scraping completed: {len(all_data)} documents")
        return all_data

    def _fetch_page(self, url: str) -> Optional[requests.Response]:
        """Fetch a web page with retry mechanism."""
        for attempt in range(self.max_retries):
            try:
                response = self.session.get(url, timeout=30)
                response.raise_for_status()
                return response
            except requests.RequestException as e:
                logger.warning(f"Attempt {attempt + 1}/{self.max_retries} failed for {url}: {e}")
                if attempt < self.max_retries - 1:
                    time.sleep(self.delay * 2)
        logger.error(f"Failed to fetch page after {self.max_retries} attempts: {url}")
        return None

    def _get_total_results(self, soup: BeautifulSoup) -> int:
        """Get total number of search results."""
        controls = soup.find("div", class_="ep_search_controls")
        if controls:
            numbers = controls.find_all("span", class_="ep_search_number")
            if len(numbers) >= 3:
                try:
                    return int(numbers[2].text.strip())
                except ValueError:
                    pass
        return 0

    def _extract_basic_info(self, row) -> Dict:
        """Extract basic info (author, year, title) from listing row."""
        tds = row.find_all("td")
        if len(tds) < 2:
            return {}

        info_td = tds[1]
        text_content = self._normalize_space(info_td.get_text(separator=" ", strip=True))

        author_span = info_td.find("span", class_="person_name")
        author = self._normalize_space(author_span.text if author_span else "")

        year_match = re.search(r"\((\d{4})\)", text_content)
        year = year_match.group(1) if year_match else ""

        link_tag = info_td.find("a", href=True)
        detail_url = ""
        title = ""
        if link_tag:
            detail_url = self._normalize_url(link_tag.get("href", ""))
            em_tag = link_tag.find("em")
            title = self._normalize_space(em_tag.text if em_tag else link_tag.text)

        return {
            "penulis_listing": author,
            "tahun_listing": year,
            "judul_listing": title,
            "detail_url": detail_url,
        }

    def _parse_detail_page(self, url: str) -> Optional[Dict]:
        """Extract detailed data from individual thesis page."""
        normalized_url = self._normalize_url(url)
        response = self._fetch_page(normalized_url)
        if not response:
            return None

        soup = BeautifulSoup(response.content, "html.parser")
        data = {}

        # Title
        h1_tags = soup.find_all("h1")
        judul = ""
        for h1 in h1_tags:
            text = self._normalize_space(h1.get_text(strip=True))
            if text and text not in [
                "Welcome to",
                "Repository Universitas Jenderal Soedirman",
            ]:
                judul = text
                break
        data["Judul"] = judul

        # Abstract
        abstract = ""
        meta_desc = soup.find("meta", attrs={"name": "eprints.abstract"})
        if meta_desc:
            abstract = self._normalize_space(meta_desc.get("content", ""))

        if not abstract:
            abstract_header = None
            for tag in soup.find_all(["h2", "h3", "h4"]):
                if "Abstract" in tag.get_text():
                    abstract_header = tag
                    break
            if abstract_header:
                abstract_parts = []
                sibling = abstract_header.find_next_sibling()
                while sibling:
                    if sibling.name in ["h1", "h2", "h3", "h4", "h5", "table"]:
                        break
                    raw_text = sibling.get_text(separator=" ", strip=True)
                    if any(
                        marker in raw_text
                        for marker in [
                            "Item Type", "ID Code", "Depositing User",
                            "Date Deposited", "Last Modified",
                            "Uncontrolled Keywords", "Nomor Inventaris",
                        ]
                    ):
                        break
                    if raw_text:
                        abstract_parts.append(raw_text)
                    sibling = sibling.find_next_sibling()
                abstract = self._normalize_space(" ".join(abstract_parts))

        data["Abstrak"] = self._normalize_space(abstract)

        # Metadata table
        metadata = {}
        tables = soup.find_all("table")
        for table in tables:
            rows = table.find_all("tr")
            for row_tag in rows:
                cells = row_tag.find_all(["th", "td"])
                if len(cells) >= 2:
                    key = cells[0].get_text(strip=True).rstrip(":")
                    value = cells[1].get_text(separator=" ", strip=True)
                    if key:
                        metadata[key] = value

        def clean_meta(val: str) -> str:
            return self._normalize_space(val)

        data["Tipe"] = clean_meta(metadata.get("Item Type", metadata.get("Type", "")))
        data["ID Code"] = clean_meta(metadata.get("ID Code", ""))
        data["Kata Kunci"] = clean_meta(
            metadata.get("Uncontrolled Keywords", metadata.get("Keywords", ""))
        )
        data["Subjects"] = clean_meta(metadata.get("Subjects", ""))
        data["Divisions"] = clean_meta(metadata.get("Divisions", ""))
        data["Tanggal Deposit"] = clean_meta(metadata.get("Date Deposited", ""))
        data["Tanggal Modifikasi"] = clean_meta(metadata.get("Last Modified", ""))
        data["URI"] = clean_meta(metadata.get("URI", ""))

        # Author & Year from citation div (e.g. "LASTNAME, Firstname (2024) Title...")
        # The citation text in ep_summary_content_main has the actual author name,
        # NOT the "Depositing User" which is the uploader (often with Mr/Mrs prefix).
        penulis = ""
        tahun = ""
        citation_div = soup.find("div", class_="ep_summary_content_main")
        if citation_div:
            citation_text = self._normalize_space(citation_div.get_text(strip=True))
            # Extract author: everything before the first (YYYY)
            author_match = re.match(r"^(.+?)\s*\(\d{4}\)", citation_text)
            if author_match:
                penulis = author_match.group(1).strip()
            # Extract year
            year_match = re.search(r"\((\d{4})\)", citation_text)
            if year_match:
                tahun = year_match.group(1)

        # Fallback to Depositing User if no author found from citation
        if not penulis:
            penulis = clean_meta(metadata.get("Depositing User", ""))
        data["Penulis"] = penulis
        if not tahun and data.get("Tanggal Deposit"):
            year_match = re.search(r"(\d{4})", data["Tanggal Deposit"])
            if year_match:
                tahun = year_match.group(1)
        data["Tahun"] = tahun

        # PDF links
        pdf_links = {}
        doc_table = None
        for table in tables:
            has_pdf = table.find("a", class_="ep_document_link")
            if has_pdf:
                doc_table = table
                break

        if not doc_table:
            for a_tag in soup.find_all("a", href=True):
                href = a_tag.get("href", "")
                if ".pdf" in href.lower():
                    text = self._normalize_space(a_tag.get_text(strip=True))
                    doc_type = self._identify_doc_type(text, href)
                    if doc_type:
                        pdf_links[doc_type] = self._normalize_url(href)
        else:
            rows = doc_table.find_all("tr")
            for row_tag in rows:
                cells = row_tag.find_all("td")
                for cell in cells:
                    text = self._normalize_space(cell.get_text(separator=" ", strip=True))
                    links_in_cell = cell.find_all("a", href=True)
                    for a_tag in links_in_cell:
                        href = a_tag.get("href", "")
                        if ".pdf" in href.lower():
                            doc_type = self._identify_doc_type(text, href)
                            if doc_type:
                                pdf_links[doc_type] = self._normalize_url(href)

        data["Dokumen_PDF"] = pdf_links
        data["URL"] = normalized_url

        return data

    def _identify_doc_type(self, text: str, href: str) -> Optional[str]:
        """Identify document type from surrounding text and file name."""
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

    def _try_extract_pdf_text(self, pdf_url: str) -> Optional[str]:
        """Try to download a PDF and extract its text."""
        try:
            response = self.session.get(pdf_url, timeout=30, allow_redirects=True)
            response.raise_for_status()
            content_type = (response.headers.get("Content-Type") or "").lower()
            if "pdf" not in content_type and ".pdf" not in pdf_url.lower():
                return None

            pdf_bytes = io.BytesIO(response.content)

            try:
                import pdfplumber

                with pdfplumber.open(pdf_bytes) as pdf:
                    text_parts = []
                    for page in pdf.pages:
                        page_text = page.extract_text() or ""
                        if page_text.strip():
                            text_parts.append(page_text)
                    if text_parts:
                        return "\n".join(text_parts).strip()
            except ImportError:
                logger.warning("pdfplumber not installed. Trying PyPDF2 fallback.")

            try:
                import PyPDF2

                reader = PyPDF2.PdfReader(pdf_bytes)
                text_parts = []
                for page in reader.pages:
                    page_text = page.extract_text() or ""
                    if page_text.strip():
                        text_parts.append(page_text)
                if text_parts:
                    return "\n".join(text_parts).strip()
            except ImportError:
                logger.warning("PyPDF2 not installed. Skipping PDF extraction.")
                return None
        except Exception as e:
            logger.warning(f"Failed to extract PDF {pdf_url}: {e}")
        return None

    def _extract_conclusion(self, text: str) -> str:
        """Extract conclusion section from Bab V/VI text."""
        if not text:
            return ""

        patterns = [
            r"(?:5\.1\.?\s*)?Kesimpulan\s*\n(.*?)(?:5\.2\.?\s*Saran|DAFTAR PUSTAKA|SARAN|$)",
            r"(?:6\.1\.?\s*)?Kesimpulan\s*\n(.*?)(?:6\.2\.?\s*Saran|DAFTAR PUSTAKA|SARAN|$)",
            r"KESIMPULAN\s*\n(.*?)(?:SARAN|DAFTAR PUSTAKA|$)",
            r"BAB\s*(?:V|VI)\s*\n.*?Kesimpulan\s*\n(.*?)(?:Saran|DAFTAR PUSTAKA|$)",
        ]

        for pattern in patterns:
            match = re.search(pattern, text, re.DOTALL | re.IGNORECASE)
            if match:
                conclusion = match.group(1).strip()
                conclusion = re.sub(r"\s+", " ", conclusion)
                return conclusion

        return ""
