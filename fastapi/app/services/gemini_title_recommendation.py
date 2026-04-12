"""Gemini-powered skripsi title recommendation service."""

import json
import re
from typing import Dict, List, Optional

from app.core.config import gemini_settings
from loguru import logger

_JSON_OBJECT_RE = re.compile(r"\{.*\}", re.DOTALL)


class GeminiTitleRecommendationError(RuntimeError):
    """Raised when Gemini title recommendation fails."""


class GeminiTitleRecommendationService:
    """Generate skripsi title recommendations from topic context."""

    @staticmethod
    def _import_gemini_sdk():
        """Import Gemini SDK lazily to avoid crashing app startup."""
        try:
            from google import genai
            from google.genai import types as genai_types

            return genai, genai_types
        except ModuleNotFoundError as exc:
            raise GeminiTitleRecommendationError(
                "Package google-genai belum terpasang di environment FastAPI. "
                "Install dependency lalu restart container."
            ) from exc

    @staticmethod
    def _normalize_title(raw_title: str) -> str:
        clean = str(raw_title).strip().strip('"').strip("'")
        clean = re.sub(r"\s+", " ", clean)

        if len(clean) < 8:
            raise GeminiTitleRecommendationError("Judul rekomendasi terlalu pendek.")

        return clean[:240]

    @staticmethod
    def _build_prompt(
        topic_label: Optional[str],
        topic_keywords: List[str],
        mapped_titles: List[str],
        user_prompt: str,
        recommendations_count: int,
    ) -> str:
        keyword_list = ", ".join(topic_keywords[:12])

        lines = [
            "Anda adalah dosen pembimbing yang membantu mahasiswa menghasilkan ide judul skripsi.",
            "Keluaran harus relevan dengan topik yang diberikan dan tetap realistis untuk skripsi S1 Teknik Informatika.",
            "Jika permintaan user di luar konteks topik skripsi, tandai context_ok=false.",
            "Gunakan daftar judul referensi sebagai konteks global utama, bukan hanya keyword.",
            "",
            "Konteks topik:",
            f"- Label topik: {topic_label or 'Topik terpilih'}",
            f"- Kata kunci topik: {keyword_list}",
            f"- Jumlah rekomendasi: {recommendations_count}",
            "",
            "Daftar judul skripsi pada topik ini (WAJIB jadi konteks utama):",
        ]

        if mapped_titles:
            for idx, title in enumerate(mapped_titles[:40], start=1):
                lines.append(f"{idx}. {title}")
        else:
            lines.append("- Tidak ada contoh judul tersedia")

        lines.extend(
            [
                "",
                "Permintaan user:",
                user_prompt,
                "",
                "Aturan:",
                "1. Jika prompt tidak relevan dengan konteks topik/skripsi, isi context_ok=false dan kosongkan recommendations.",
                "2. Jika relevan, isi context_ok=true dan gunakan gabungan konteks keyword + pola tema dari daftar judul referensi.",
                "3. Jangan menyimpulkan topik hanya dari keyword; wajib pertimbangkan kemiripan/arah riset pada daftar judul.",
                "4. Berikan judul yang spesifik, jelas, dan dapat diteliti.",
                "5. Hindari judul yang terlalu umum, bombastis, atau tidak akademik.",
                "6. Setiap rationale maksimal 1 kalimat ringkas.",
                "7. Gunakan Bahasa Indonesia formal.",
                "8. Keluarkan JSON valid saja tanpa markdown/code block.",
                "",
                "Format JSON wajib:",
                '{"context_ok":true,"concontext_oktext_message":"...","recommendations":[{"title":"...","rationale":"..."}]}',
            ]
        )

        return "\n".join(lines)

    @staticmethod
    def _extract_response_text(response) -> str:
        response_text = ""
        try:
            response_text = str(getattr(response, "text", "") or "").strip()
        except Exception:
            response_text = ""

        if response_text != "":
            return response_text

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
    def _parse_json_response(raw_text: str, expected_count: int) -> Dict[str, object]:
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
            raise GeminiTitleRecommendationError("Format respons Gemini bukan JSON yang valid.")

        context_ok = bool(parsed.get("context_ok", True))
        context_message = str(parsed.get("context_message", "") or "").strip() or None

        raw_recommendations = parsed.get("recommendations")
        recommendations: List[Dict[str, str]] = []
        seen = set()

        if isinstance(raw_recommendations, list):
            for item in raw_recommendations:
                if not isinstance(item, dict):
                    continue

                try:
                    title = GeminiTitleRecommendationService._normalize_title(str(item.get("title", "") or ""))
                except GeminiTitleRecommendationError:
                    continue

                rationale = str(item.get("rationale", "") or "").strip()
                if rationale == "":
                    rationale = "Judul ini sesuai dengan fokus topik dan kebutuhan riset skripsi."

                key = title.lower()
                if key in seen:
                    continue

                seen.add(key)
                recommendations.append(
                    {
                        "title": title,
                        "rationale": rationale[:600],
                    }
                )

                if len(recommendations) >= max(3, min(10, expected_count)):
                    break

        if context_ok and not recommendations:
            raise GeminiTitleRecommendationError("Respons Gemini tidak memuat rekomendasi judul yang valid.")

        if not context_ok and context_message is None:
            context_message = "Prompt terdeteksi di luar konteks topik skripsi."

        return {
            "context_ok": context_ok,
            "context_message": context_message,
            "recommendations": recommendations,
        }

    def generate_recommendations(
        self,
        topic_keywords: List[str],
        user_prompt: str,
        topic_label: Optional[str] = None,
        mapped_titles: Optional[List[str]] = None,
        recommendations_count: int = 5,
    ) -> Dict[str, object]:
        clean_keywords = [str(keyword).strip() for keyword in topic_keywords if str(keyword).strip()]
        if not clean_keywords:
            raise ValueError("Kata kunci topik tidak tersedia untuk AI.")

        clean_prompt = str(user_prompt or "").strip()
        if clean_prompt == "":
            raise ValueError("Prompt tidak boleh kosong.")

        clean_titles = [str(title).strip() for title in (mapped_titles or []) if str(title).strip()]
        clean_label = str(topic_label or "").strip() or None
        clean_count = max(3, min(10, int(recommendations_count or 5)))

        genai, genai_types = self._import_gemini_sdk()

        api_key = str(gemini_settings.GEMINI_API_KEY or "").strip()
        if api_key == "":
            raise GeminiTitleRecommendationError("Gemini API key belum dikonfigurasi di FastAPI.")

        model_name = str(gemini_settings.GEMINI_MODEL or "gemini-2.5-flash").strip()
        if model_name == "":
            model_name = "gemini-2.5-flash"

        try:
            client = genai.Client(api_key=api_key)
            response = client.models.generate_content(
                model=model_name,
                contents=self._build_prompt(
                    topic_label=clean_label,
                    topic_keywords=clean_keywords,
                    mapped_titles=clean_titles,
                    user_prompt=clean_prompt,
                    recommendations_count=clean_count,
                ),
                config=genai_types.GenerateContentConfig(
                    temperature=0.35,
                    response_mime_type="application/json",
                ),
            )
        except Exception as exc:
            logger.exception("Gemini API request failed during title recommendation")

            error_text = str(exc).lower()
            if (
                "no longer available to new users" in error_text
                or ("model" in error_text and "not found" in error_text)
            ):
                raise GeminiTitleRecommendationError(
                    f"Model Gemini '{model_name}' tidak tersedia untuk API key ini. "
                    "Ganti GEMINI_MODEL ke model yang didukung, misalnya gemini-2.5-flash."
                ) from exc

            raise GeminiTitleRecommendationError("Gagal menghubungi Gemini dari FastAPI.") from exc

        response_text = self._extract_response_text(response)
        if response_text == "":
            raise GeminiTitleRecommendationError("Respons Gemini kosong atau tidak valid.")

        return self._parse_json_response(response_text, expected_count=clean_count)


gemini_title_recommendation_service = GeminiTitleRecommendationService()
