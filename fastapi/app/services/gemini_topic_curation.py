"""Gemini-powered topic curation suggestion service."""

import json
import re
from typing import Dict, List, Optional

from app.core.config import gemini_settings
from loguru import logger

_JSON_OBJECT_RE = re.compile(r"\{.*\}", re.DOTALL)


class GeminiTopicCurationError(RuntimeError):
    """Raised when Gemini suggestion generation fails."""


class GeminiTopicCurationService:
    """Generate topic name and representation description from topic context."""

    @staticmethod
    def _normalize_theme_name(raw_name: str) -> str:
        """Normalize AI output into a short 2-4 word theme phrase."""
        clean = re.sub(r"[^\w\s\-]", " ", str(raw_name), flags=re.UNICODE)
        clean = re.sub(r"\s+", " ", clean).strip()

        words = [word for word in clean.split(" ") if word.strip()]
        if len(words) < 2:
            raise GeminiTopicCurationError(
                "custom_name terlalu pendek. Gunakan frasa tema 2-4 kata."
            )

        if len(words) > 4:
            words = words[:4]

        return " ".join(words)

    @staticmethod
    def _import_gemini_sdk():
        """Import Gemini SDK lazily to avoid crashing app startup."""
        try:
            from google import genai
            from google.genai import types as genai_types

            return genai, genai_types
        except ModuleNotFoundError as exc:
            raise GeminiTopicCurationError(
                "Package google-genai belum terpasang di environment FastAPI. "
                "Install dependency lalu restart container."
            ) from exc

    @staticmethod
    def _build_prompt(
        keywords: List[str],
        topic_id: Optional[int] = None,
        topic_doc_count: Optional[int] = None,
        model_type: Optional[str] = None,
        representative_titles: Optional[List[str]] = None,
        representative_abstracts: Optional[List[str]] = None,
        broader_terms: Optional[List[str]] = None,
    ) -> str:
        keyword_list = ", ".join(keywords[:15])
        titles = [title.strip() for title in (representative_titles or []) if str(title).strip()]

        lines = [
            "Anda adalah asisten kurasi topik skripsi berbahasa Indonesia.",
            "Tugas: buat nama topik dan deskripsi representasi yang lebih global, bukan judul panjang.",
            "Gunakan konteks kata kunci utama dan judul-judul referensi untuk menangkap tema umum.",
            "",
            "Konteks topik:",
            f"Kata kunci utama: {keyword_list}",
            "List judul referensi:",
        ]

        if titles:
            for idx, title in enumerate(titles, start=1):
                lines.append(f"{idx}. {title}")
        else:
            lines.append("- Tidak ada judul referensi tersedia")

        lines.extend([
            "",
            "Aturan output:",
            "1. custom_name: WAJIB 2-4 kata, berupa frasa tema singkat (bukan judul panjang).",
            "2. representation_description: 1-2 kalimat yang merangkum tema umum berdasarkan kata kunci dan list judul referensi.",
            "3. Hindari nama/deskripsi yang sekadar daftar keyword mentah.",
            "4. Gunakan Bahasa Indonesia formal.",
            "5. Keluarkan JSON valid saja tanpa markdown/code block.",
            "",
            "Format wajib:",
            '{"custom_name":"...","representation_description":"..."}',
        ])

        return "\n".join(lines)

    @staticmethod
    def _extract_response_text(response) -> str:
        # Primary path for google-genai responses.
        response_text = ""
        try:
            response_text = str(getattr(response, "text", "") or "").strip()
        except Exception:
            response_text = ""

        if response_text != "":
            return response_text

        # Fallback path when response.text is empty but candidates/parts are present.
        candidates = getattr(response, "candidates", None) or []
        for candidate in candidates:
            content = getattr(candidate, "content", None)
            parts = getattr(content, "parts", None) or []
            for part in parts:
                text = str(getattr(part, "text", "") or "").strip()
                if text != "":
                    return text

        return ""

    @staticmethod
    def _parse_json_response(raw_text: str) -> Dict[str, str]:
        clean_text = raw_text.strip()

        if clean_text.startswith("```"):
            clean_text = re.sub(r"^```[a-zA-Z]*\s*", "", clean_text)
            clean_text = re.sub(r"\s*```$", "", clean_text)
            clean_text = clean_text.strip()

        parsed = None
        try:
            parsed = json.loads(clean_text)
        except Exception:
            parsed = None

        if not isinstance(parsed, dict):
            match = _JSON_OBJECT_RE.search(clean_text)
            if match:
                try:
                    parsed = json.loads(match.group(0))
                except Exception:
                    parsed = None

        if not isinstance(parsed, dict):
            raise GeminiTopicCurationError("Format respons Gemini bukan JSON yang valid.")

        custom_name = str(parsed.get("custom_name", "") or "").strip()
        representation_description = str(parsed.get("representation_description", "") or "").strip()

        if custom_name == "" or representation_description == "":
            raise GeminiTopicCurationError(
                "Respons Gemini tidak memuat custom_name atau representation_description."
            )

        custom_name = GeminiTopicCurationService._normalize_theme_name(custom_name)

        return {
            "custom_name": custom_name[:150],
            "representation_description": representation_description[:2000],
        }

    def generate_suggestion(
        self,
        keywords: List[str],
        topic_id: Optional[int] = None,
        topic_doc_count: Optional[int] = None,
        model_type: Optional[str] = None,
        representative_titles: Optional[List[str]] = None,
        representative_abstracts: Optional[List[str]] = None,
        broader_terms: Optional[List[str]] = None,
    ) -> Dict[str, str]:
        clean_keywords = [str(keyword).strip() for keyword in keywords if str(keyword).strip()]
        if not clean_keywords:
            raise ValueError("Kata kunci topik tidak tersedia untuk AI.")

        clean_titles = [str(title).strip() for title in (representative_titles or []) if str(title).strip()]
        clean_abstracts = [str(text).strip() for text in (representative_abstracts or []) if str(text).strip()]
        clean_broader_terms = [str(term).strip() for term in (broader_terms or []) if str(term).strip()]
        clean_model_type = str(model_type or "").strip().lower() or None

        genai, genai_types = self._import_gemini_sdk()

        api_key = str(gemini_settings.GEMINI_API_KEY or "").strip()
        if api_key == "":
            raise GeminiTopicCurationError("Gemini API key belum dikonfigurasi di FastAPI.")

        model_name = str(gemini_settings.GEMINI_MODEL or "gemini-2.5-flash").strip()
        if model_name == "":
            model_name = "gemini-2.5-flash"

        try:
            client = genai.Client(api_key=api_key)
            response = client.models.generate_content(
                model=model_name,
                contents=self._build_prompt(
                    keywords=clean_keywords,
                    topic_id=topic_id,
                    topic_doc_count=topic_doc_count,
                    model_type=clean_model_type,
                    representative_titles=clean_titles,
                    representative_abstracts=clean_abstracts,
                    broader_terms=clean_broader_terms,
                ),
                config=genai_types.GenerateContentConfig(
                    temperature=0.2,
                    response_mime_type="application/json",
                ),
            )
        except Exception as exc:
            logger.exception("Gemini API request failed during topic curation")

            error_text = str(exc).lower()
            if (
                "no longer available to new users" in error_text
                or ("model" in error_text and "not found" in error_text)
            ):
                raise GeminiTopicCurationError(
                    f"Model Gemini '{model_name}' tidak tersedia untuk API key ini. "
                    "Ganti GEMINI_MODEL ke model yang didukung, misalnya gemini-2.5-flash."
                ) from exc

            raise GeminiTopicCurationError("Gagal menghubungi Gemini dari FastAPI.") from exc

        response_text = self._extract_response_text(response)
        if response_text == "":
            raise GeminiTopicCurationError("Respons Gemini kosong atau tidak valid.")

        return self._parse_json_response(response_text)


gemini_topic_curation_service = GeminiTopicCurationService()
