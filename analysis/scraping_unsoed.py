"""
Scraping Repository UNSOED - S1 Teknik Informatika
===================================================
Menggunakan BeautifulSoup untuk mengambil data skripsi dari repository UNSOED.
Data yang diambil: Judul, Abstract, Tahun, Kesimpulan, Penulis, dll.
Hasil disimpan dalam folder data/
"""

import json
import logging
import os
import re
import time
from datetime import datetime
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup

# ===================== KONFIGURASI =====================
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
OUTPUT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data")
OUTPUT_FILE = os.path.join(OUTPUT_DIR, "skripsi_unsoed_scraped.json")
DELAY_BETWEEN_REQUESTS = 2  # detik, agar tidak membebani server
MAX_RETRIES = 3

# ===================== LOGGING =====================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler(os.path.join(os.path.dirname(os.path.abspath(__file__)), "scraping.log"), 
                          encoding='utf-8')
    ]
)
logger = logging.getLogger(__name__)

# ===================== SESSION =====================
session = requests.Session()
session.headers.update({
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                  '(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language': 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
})


def fetch_page(url, retries=MAX_RETRIES):
    """Mengambil halaman web dengan retry mechanism."""
    for attempt in range(retries):
        try:
            response = session.get(url, timeout=30)
            response.raise_for_status()
            return response
        except requests.RequestException as e:
            logger.warning(f"Attempt {attempt + 1}/{retries} gagal untuk {url}: {e}")
            if attempt < retries - 1:
                time.sleep(DELAY_BETWEEN_REQUESTS * 2)
            else:
                logger.error(f"Gagal mengambil halaman setelah {retries} percobaan: {url}")
                return None


def get_total_results(soup):
    """Mendapatkan total jumlah hasil pencarian."""
    controls = soup.find("div", class_="ep_search_controls")
    if controls:
        numbers = controls.find_all("span", class_="ep_search_number")
        if len(numbers) >= 3:
            try:
                return int(numbers[2].text.strip())
            except ValueError:
                pass
    return 0


def get_detail_links_from_page(soup):
    """Mengambil semua link detail skripsi dari halaman pencarian."""
    links = []
    results_div = soup.find("div", class_="ep_search_results")
    if not results_div:
        return links
    
    rows = results_div.find_all("tr", class_="ep_search_result")
    for row in rows:
        # Kolom kedua berisi info skripsi
        tds = row.find_all("td")
        if len(tds) >= 2:
            info_td = tds[1]
            # Cari link ke halaman detail (biasanya link pada judul <em>)
            link_tag = info_td.find("a", href=True)
            if link_tag:
                href = link_tag.get("href", "")
                if href.startswith("https://repository.unsoed.ac.id/"):
                    links.append(href)
                elif href.startswith("/"):
                    links.append(urljoin(BASE_URL, href))
            
            # Juga ambil info dasar dari listing (penulis dan tahun)
            # Format: <span class="person_name">NAMA</span> (TAHUN) <a>judul</a>
    
    return links


def extract_basic_info_from_listing(row):
    """Mengambil info dasar (penulis, tahun) dari baris listing."""
    tds = row.find_all("td")
    if len(tds) < 2:
        return {}
    
    info_td = tds[1]
    text_content = info_td.get_text(separator=" ", strip=True)
    
    # Ambil penulis dari span.person_name
    author_span = info_td.find("span", class_="person_name")
    author = author_span.text.strip() if author_span else ""
    
    # Ambil tahun (biasanya dalam format "(YYYY)")
    year_match = re.search(r'\((\d{4})\)', text_content)
    year = year_match.group(1) if year_match else ""
    
    # Ambil link detail
    link_tag = info_td.find("a", href=True)
    detail_url = ""
    title = ""
    if link_tag:
        detail_url = link_tag.get("href", "")
        em_tag = link_tag.find("em")
        title = em_tag.text.strip() if em_tag else link_tag.text.strip()
    
    return {
        "penulis_listing": author,
        "tahun_listing": year,
        "judul_listing": title,
        "detail_url": detail_url
    }


def parse_detail_page(url):
    """Mengambil data detail dari halaman individual skripsi."""
    logger.info(f"  Mengambil detail: {url}")
    response = fetch_page(url)
    if not response:
        return None
    
    soup = BeautifulSoup(response.content, 'html.parser')
    data = {}
    
    # ---- JUDUL ----
    # Judul biasanya ada di tag <h1> atau di <title> atau di div tertentu
    # Pada EPrints, judul ada di eprint_fieldname_title
    title_div = soup.find("div", class_="ep_summary_content")
    if not title_div:
        title_div = soup  # fallback ke seluruh halaman
    
    # Coba ambil dari h1
    h1_tags = soup.find_all("h1")
    judul = ""
    for h1 in h1_tags:
        text = h1.get_text(strip=True)
        # Skip h1 yang merupakan "Welcome to" atau navigasi
        if text and text not in ["Welcome to", "Repository Universitas Jenderal Soedirman"]:
            judul = text
            break
    data["Judul"] = judul
    
    # ---- ABSTRACT ----
    # Prioritas 1: meta tag eprints.abstract — paling bersih, tidak mengandung metadata
    abstract = ""
    meta_desc = soup.find("meta", attrs={"name": "eprints.abstract"})
    if meta_desc:
        abstract = meta_desc.get("content", "").strip()
    
    # Prioritas 2: cari heading "Abstract" lalu ambil HANYA paragraf <p> tepat setelahnya
    # (bukan semua sibling, karena sibling berikutnya bisa berisi tabel metadata EPrints)
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
                # Berhenti jika menemukan heading baru atau tabel metadata
                if sibling.name in ["h1", "h2", "h3", "h4", "h5", "table"]:
                    break
                # Berhenti jika teks mengandung marker metadata EPrints
                raw_text = sibling.get_text(separator=" ", strip=True)
                if any(marker in raw_text for marker in [
                    "Item Type", "ID Code", "Depositing User",
                    "Date Deposited", "Last Modified", "Uncontrolled Keywords",
                    "Nomor Inventaris",
                ]):
                    break
                if raw_text:
                    abstract_parts.append(raw_text)
                sibling = sibling.find_next_sibling()
            abstract = " ".join(abstract_parts).strip()
    
    # Prioritas 3: cari di div dengan class ep_block yang berisi label "Abstract"
    if not abstract:
        for ep_block in soup.find_all("div", class_="ep_block"):
            label = ep_block.find(["h2", "h3", "h4", "span", "b"])
            if label and "Abstract" in label.get_text():
                # Ambil teks dari div ini, kecuali label-nya sendiri
                label.extract()
                abstract = ep_block.get_text(separator=" ", strip=True)
                break
    
    data["Abstrak"] = abstract.strip()
    
    # ---- METADATA TABLE ----
    # EPrints menyimpan metadata dalam table
    # Cari tabel yang berisi Type, ID Code, Keywords, Subjects, Divisions, Depositing User, Date Deposited
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
        """Normalisasi nilai metadata: hapus newline berlebih & whitespace."""
        return re.sub(r"\s+", " ", val).strip()
    
    # Ambil data dari metadata — nilai dibersihkan dari whitespace/newline
    data["Tipe"]               = clean_meta(metadata.get("Item Type", metadata.get("Type", "")))
    data["ID Code"]            = clean_meta(metadata.get("ID Code", ""))
    data["Kata Kunci"]         = clean_meta(metadata.get("Uncontrolled Keywords", metadata.get("Keywords", "")))
    data["Subjects"]           = clean_meta(metadata.get("Subjects", ""))
    data["Divisions"]          = clean_meta(metadata.get("Divisions", ""))
    data["Penulis"]            = clean_meta(metadata.get("Depositing User", ""))
    data["Tanggal Deposit"]    = clean_meta(metadata.get("Date Deposited", ""))
    data["Tanggal Modifikasi"] = clean_meta(metadata.get("Last Modified", ""))
    data["URI"]                = clean_meta(metadata.get("URI", ""))
    
    # ---- TAHUN ----
    # Ambil tahun dari judul halaman atau dari metadata
    tahun = ""
    # Dari citation text di halaman
    citation_div = soup.find("div", class_="ep_summary_content_main")
    if citation_div:
        citation_text = citation_div.get_text()
        year_match = re.search(r'\((\d{4})\)', citation_text)
        if year_match:
            tahun = year_match.group(1)
    
    # Fallback dari Date Deposited
    if not tahun and data.get("Tanggal Deposit"):
        year_match = re.search(r'(\d{4})', data["Tanggal Deposit"])
        if year_match:
            tahun = year_match.group(1)
    
    data["Tahun"] = tahun
    
    # ---- LINK DOKUMEN (PDF) ----
    # Cari link ke PDF-PDF yang tersedia (Cover, Abstrak, Bab V/Kesimpulan, dll)
    pdf_links = {}
    doc_table = None
    
    # Cari tabel yang berisi link dokumen
    for table in tables:
        has_pdf = table.find("a", class_="ep_document_link")
        if has_pdf:
            doc_table = table
            break
    
    if not doc_table:
        # Fallback: cari semua link PDF di halaman
        for a_tag in soup.find_all("a", href=True):
            href = a_tag.get("href", "")
            if href.endswith(".pdf"):
                text = a_tag.get_text(strip=True)
                if text:
                    pdf_links[text] = urljoin(BASE_URL, href)
    else:
        rows = doc_table.find_all("tr")
        for row_tag in rows:
            cells = row_tag.find_all("td")
            for cell in cells:
                text = cell.get_text(separator=" ", strip=True)
                links_in_cell = cell.find_all("a", href=True)
                for a_tag in links_in_cell:
                    href = a_tag.get("href", "")
                    if href.endswith(".pdf"):
                        # Identifikasi jenis dokumen
                        doc_type = identify_doc_type(text, href)
                        if doc_type:
                            pdf_links[doc_type] = urljoin(BASE_URL, href)
    
    data["Dokumen_PDF"] = pdf_links
    
    # ---- URL ----
    data["URL"] = url
    
    return data


def identify_doc_type(text, href):
    """Identifikasi jenis dokumen berdasarkan teks dan URL."""
    text_lower = text.lower()
    href_lower = href.lower()
    
    patterns = {
        "Cover": ["cover"],
        "Legalitas": ["legalitas", "legal"],
        "Abstrak": ["abstrak", "abstract"],
        "Bab I": ["bab-i", "bab_i", "bab i", "babi"],
        "Bab II": ["bab-ii", "bab_ii", "bab ii", "babii"],
        "Bab III": ["bab-iii", "bab_iii", "bab iii", "babiii"],
        "Bab IV": ["bab-iv", "bab_iv", "bab iv", "babiv"],
        "Bab V": ["bab-v", "bab_v", "bab v", "babv"],
        "Bab VI": ["bab-vi", "bab_vi", "bab vi", "babvi"],
        "Daftar Pustaka": ["daftar pustaka", "daftar_pustaka", "daftarpustaka", "references"],
        "Lampiran": ["lampiran", "appendix"],
    }
    
    combined = text_lower + " " + href_lower
    for doc_type, keywords in patterns.items():
        for kw in keywords:
            if kw in combined:
                return doc_type
    
    return None


def try_download_and_extract_text(pdf_url):
    """
    Mencoba mengunduh PDF dan mengekstrak teksnya.
    Mengembalikan teks atau None jika gagal/restricted.
    """
    try:
        response = session.get(pdf_url, timeout=30, allow_redirects=True)
        if response.status_code == 200 and 'application/pdf' in response.headers.get('Content-Type', ''):
            # Coba ekstrak teks menggunakan PyPDF2 atau pdfplumber
            try:
                import io
                try:
                    import pdfplumber
                    with pdfplumber.open(io.BytesIO(response.content)) as pdf:
                        text_parts = []
                        for page in pdf.pages:
                            page_text = page.extract_text()
                            if page_text:
                                text_parts.append(page_text)
                        return "\n".join(text_parts) if text_parts else None
                except ImportError:
                    try:
                        from PyPDF2 import PdfReader
                        reader = PdfReader(io.BytesIO(response.content))
                        text_parts = []
                        for page in reader.pages:
                            page_text = page.extract_text()
                            if page_text:
                                text_parts.append(page_text)
                        return "\n".join(text_parts) if text_parts else None
                    except ImportError:
                        logger.warning("Tidak ada library PDF reader (pdfplumber/PyPDF2). "
                                     "Install dengan: pip install pdfplumber")
                        return None
            except Exception as e:
                logger.warning(f"Gagal mengekstrak PDF: {e}")
                return None
        else:
            logger.debug(f"PDF restricted atau tidak tersedia: {pdf_url}")
            return None
    except Exception as e:
        logger.warning(f"Gagal mengunduh PDF {pdf_url}: {e}")
        return None


def extract_conclusion_from_text(text):
    """Mengekstrak bagian kesimpulan dari teks Bab V/VI."""
    if not text:
        return ""
    
    # Cari pattern kesimpulan
    patterns = [
        r'(?:5\.1\.?\s*)?Kesimpulan\s*\n(.*?)(?:5\.2\.?\s*Saran|DAFTAR PUSTAKA|SARAN|$)',
        r'(?:6\.1\.?\s*)?Kesimpulan\s*\n(.*?)(?:6\.2\.?\s*Saran|DAFTAR PUSTAKA|SARAN|$)',
        r'KESIMPULAN\s*\n(.*?)(?:SARAN|DAFTAR PUSTAKA|$)',
        r'BAB\s*(?:V|VI)\s*\n.*?Kesimpulan\s*\n(.*?)(?:Saran|DAFTAR PUSTAKA|$)',
    ]
    
    for pattern in patterns:
        match = re.search(pattern, text, re.DOTALL | re.IGNORECASE)
        if match:
            conclusion = match.group(1).strip()
            # Bersihkan teks
            conclusion = re.sub(r'\s+', ' ', conclusion)
            return conclusion
    
    return text.strip()


def scrape_all():
    """Fungsi utama untuk melakukan scraping seluruh data skripsi."""
    logger.info("=" * 60)
    logger.info("MULAI SCRAPING REPOSITORY UNSOED - S1 TEKNIK INFORMATIKA")
    logger.info("=" * 60)
    
    # Pastikan folder output ada
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    
    # ---- STEP 1: Ambil halaman pertama untuk mengetahui total data ----
    logger.info("Mengambil halaman pertama untuk mengetahui total data...")
    first_page_url = f"{SEARCH_URL}&search_offset=0"
    response = fetch_page(first_page_url)
    if not response:
        logger.error("Gagal mengambil halaman pertama!")
        return
    
    soup = BeautifulSoup(response.content, 'html.parser')
    total_results = get_total_results(soup)
    logger.info(f"Total skripsi ditemukan: {total_results}")
    
    if total_results == 0:
        logger.error("Tidak ada data ditemukan!")
        return
    
    total_pages = (total_results + ITEMS_PER_PAGE - 1) // ITEMS_PER_PAGE
    logger.info(f"Total halaman: {total_pages}")
    
    # ---- STEP 2: Kumpulkan semua link detail dari setiap halaman ----
    all_detail_urls = []
    all_basic_info = []
    
    for page_num in range(total_pages):
        offset = page_num * ITEMS_PER_PAGE
        page_url = f"{SEARCH_URL}&search_offset={offset}"
        
        logger.info(f"\n--- Halaman {page_num + 1}/{total_pages} (offset: {offset}) ---")
        
        if page_num == 0:
            # Sudah punya halaman pertama
            page_soup = soup
        else:
            time.sleep(DELAY_BETWEEN_REQUESTS)
            response = fetch_page(page_url)
            if not response:
                logger.warning(f"Gagal mengambil halaman {page_num + 1}, skip...")
                continue
            page_soup = BeautifulSoup(response.content, 'html.parser')
        
        # Ambil link detail dan info dasar
        results_div = page_soup.find("div", class_="ep_search_results")
        if results_div:
            rows = results_div.find_all("tr", class_="ep_search_result")
            for row in rows:
                basic_info = extract_basic_info_from_listing(row)
                if basic_info.get("detail_url"):
                    all_detail_urls.append(basic_info["detail_url"])
                    all_basic_info.append(basic_info)
        
        logger.info(f"  Ditemukan {len(rows) if results_div else 0} item di halaman ini. "
                    f"Total terkumpul: {len(all_detail_urls)}")
    
    logger.info(f"\nTotal link detail terkumpul: {len(all_detail_urls)}")
    
    # ---- STEP 3: Kunjungi setiap halaman detail dan ambil data ----
    all_data = []
    
    # Cek apakah ada data sebelumnya (untuk resume)
    if os.path.exists(OUTPUT_FILE):
        try:
            with open(OUTPUT_FILE, 'r', encoding='utf-8') as f:
                existing_data = json.load(f)
            existing_urls = {item.get("URL", "") for item in existing_data}
            logger.info(f"Ditemukan {len(existing_data)} data yang sudah discrape sebelumnya.")
            all_data = existing_data
        except (json.JSONDecodeError, Exception):
            existing_urls = set()
    else:
        existing_urls = set()
    
    new_count = 0
    for idx, detail_url in enumerate(all_detail_urls):
        # Skip jika sudah pernah discrape
        if detail_url in existing_urls:
            logger.info(f"[{idx + 1}/{len(all_detail_urls)}] Sudah ada, skip: {detail_url}")
            continue
        
        logger.info(f"\n[{idx + 1}/{len(all_detail_urls)}] Scraping detail...")
        time.sleep(DELAY_BETWEEN_REQUESTS)
        
        detail_data = parse_detail_page(detail_url)
        if detail_data:
            # Tambahkan info dari listing jika data dari detail kosong
            basic = all_basic_info[idx] if idx < len(all_basic_info) else {}
            if not detail_data.get("Judul") and basic.get("judul_listing"):
                detail_data["Judul"] = basic["judul_listing"]
            if not detail_data.get("Tahun") and basic.get("tahun_listing"):
                detail_data["Tahun"] = basic["tahun_listing"]
            if not detail_data.get("Penulis") and basic.get("penulis_listing"):
                detail_data["Penulis"] = basic["penulis_listing"]
            
            # ---- Coba ambil kesimpulan dari PDF Bab V ----
            kesimpulan = ""
            sumber_kesimpulan = ""
            
            pdf_docs = detail_data.get("Dokumen_PDF", {})
            
            # Prioritas: Bab V > Bab VI
            for bab_key in ["Bab V", "Bab VI"]:
                if bab_key in pdf_docs:
                    logger.info(f"  Mencoba mengunduh {bab_key}...")
                    text = try_download_and_extract_text(pdf_docs[bab_key])
                    if text:
                        kesimpulan = extract_conclusion_from_text(text)
                        sumber_kesimpulan = f"{bab_key} (PDF)"
                        logger.info(f"  ✓ Berhasil mengekstrak kesimpulan dari {bab_key}")
                        break
                    else:
                        logger.info(f"  ✗ {bab_key} restricted atau gagal diunduh")
            
            detail_data["Kesimpulan"] = kesimpulan
            detail_data["Sumber Kesimpulan"] = sumber_kesimpulan
            
            all_data.append(detail_data)
            new_count += 1
            
            # Simpan secara berkala setiap 10 item baru
            if new_count % 10 == 0:
                save_data(all_data)
                logger.info(f"  💾 Data disimpan (total: {len(all_data)})")
    
    # ---- STEP 4: Simpan data final ----
    save_data(all_data)
    
    logger.info("\n" + "=" * 60)
    logger.info("SCRAPING SELESAI!")
    logger.info(f"Total data terscrape: {len(all_data)}")
    logger.info(f"Data baru ditambahkan: {new_count}")
    logger.info(f"File disimpan di: {OUTPUT_FILE}")
    logger.info("=" * 60)


def save_data(data):
    """Simpan data ke file JSON."""
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def print_summary(data):
    """Cetak ringkasan data yang sudah terscrape."""
    print("\n" + "=" * 60)
    print("RINGKASAN DATA SCRAPING")
    print("=" * 60)
    print(f"Total data: {len(data)}")
    
    # Hitung per tahun
    years = {}
    for item in data:
        year = item.get("Tahun", "Unknown")
        years[year] = years.get(year, 0) + 1
    
    print("\nDistribusi per tahun:")
    for year in sorted(years.keys(), reverse=True):
        print(f"  {year}: {years[year]} skripsi")
    
    # Hitung yang punya abstract
    with_abstract = sum(1 for item in data if item.get("Abstrak"))
    with_conclusion = sum(1 for item in data if item.get("Kesimpulan"))
    
    print(f"\nDengan abstrak: {with_abstract}/{len(data)}")
    print(f"Dengan kesimpulan: {with_conclusion}/{len(data)}")
    print("=" * 60)


def clean_existing_json(filepath=OUTPUT_FILE):
    """
    Membersihkan data JSON yang sudah ada dari artifact metadata scraping.
    Gunakan ini untuk memperbaiki data lama TANPA perlu scraping ulang.
    
    Artifact yang dibersihkan dari field Abstrak:
    - "Item Type:Thesis\\n\\n\\n(Skripsi)Nomor Inventaris:H26xxx..."
    - "Depositing User:...", "Date Deposited:...", "URI:...", dst.
    
    Field Tipe juga dinormalisasi (hapus newline berlebih).
    """
    if not os.path.exists(filepath):
        logger.error(f"File tidak ditemukan: {filepath}")
        return
    
    with open(filepath, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    logger.info(f"Membersihkan {len(data)} record dari: {filepath}")
    
    # Regex untuk hapus blok metadata EPrints yang ikut ter-scrape di field Abstrak
    # Pattern: mulai dari "Item Type:" sampai akhir string
    artifact_pattern = re.compile(
        r"Item\s+Type\s*:.*$",
        flags=re.DOTALL | re.IGNORECASE
    )
    # Pattern tambahan untuk sisa metadata yang mungkin ada
    extra_patterns = [
        re.compile(r"Nomor\s+Inventaris\s*:\s*\S+", re.IGNORECASE),
        re.compile(r"Uncontrolled\s+Keywords\s*:[^\n]*", re.IGNORECASE),
        re.compile(r"Depositing\s+User\s*:[^\n]*", re.IGNORECASE),
        re.compile(r"Date\s+Deposited\s*:[^\n]*", re.IGNORECASE),
        re.compile(r"Last\s+Modified\s*:[^\n]*", re.IGNORECASE),
        re.compile(r"URI\s*:\s*http\S*", re.IGNORECASE),
    ]
    
    cleaned_count = 0
    for item in data:
        original_abstract = item.get("Abstrak", "")
        
        # Hapus blok artifact utama
        cleaned = artifact_pattern.sub("", original_abstract)
        
        # Hapus sisa pattern metadata
        for pat in extra_patterns:
            cleaned = pat.sub("", cleaned)
        
        # Normalisasi whitespace
        cleaned = re.sub(r"\s+", " ", cleaned).strip()
        
        if cleaned != original_abstract:
            item["Abstrak"] = cleaned
            cleaned_count += 1
        
        # Normalisasi field Tipe (hapus newline \n\n\n yang mengotori nilai)
        if "Tipe" in item:
            item["Tipe"] = re.sub(r"\s+", " ", item["Tipe"]).strip()
    
    # Simpan kembali
    with open(filepath, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    
    logger.info(f"✅ Selesai. {cleaned_count} record dibersihkan. File disimpan: {filepath}")


# ===================== MAIN =====================
if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Scraper Repository UNSOED")
    parser.add_argument(
        "--clean-only",
        action="store_true",
        help="Hanya bersihkan data JSON yang sudah ada (tanpa scraping ulang)",
    )
    args = parser.parse_args()
    
    if args.clean_only:
        # Mode: bersihkan data lama saja
        logger.info("Mode: clean-only — membersihkan artifact dari data JSON yang sudah ada...")
        clean_existing_json()
    else:
        start_time = datetime.now()
        logger.info(f"Waktu mulai: {start_time}")
        
        try:
            scrape_all()
        except KeyboardInterrupt:
            logger.info("\n⚠ Scraping dihentikan oleh pengguna. Data yang sudah ada tetap tersimpan.")
        except Exception as e:
            logger.error(f"Error tidak terduga: {e}", exc_info=True)
        
        end_time = datetime.now()
        duration = end_time - start_time
        logger.info(f"Waktu selesai: {end_time}")
        logger.info(f"Durasi: {duration}")
        
        # Tampilkan ringkasan jika ada data
        if os.path.exists(OUTPUT_FILE):
            with open(OUTPUT_FILE, 'r', encoding='utf-8') as f:
                data = json.load(f)
            print_summary(data)
