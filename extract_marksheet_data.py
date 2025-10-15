#!/usr/bin/env python3
"""
Marksheet Data Extraction Script
- Extracts data from uploaded PDF marksheets (text-based and image-based)
- Parses semester 6 (and any semester) subject rows like:
  CS3210 Operating Systems 4 A 34
- Returns JSON consumed by the PHP uploader.
"""

import sys
import json
import os
import re
from pathlib import Path

try:
    import PyPDF2
    import pytesseract
    from PIL import Image
    import pdf2image
except ImportError as e:
    print(json.dumps({
        "success": False,
        "error": f"Required library not installed: {e}",
        "install_command": "pip install PyPDF2 pytesseract Pillow pdf2image"
    }))
    sys.exit(1)


def extract_text_from_pdf(pdf_path: str) -> str:
    """Extract text from a PDF using PyPDF2 first; fallback to OCR (pdf2image + Tesseract).
    Returns raw extracted text.
    """
    text = ""

    # Try PyPDF2 for text-based PDFs
    try:
        with open(pdf_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            for page in reader.pages:
                try:
                    t = page.extract_text() or ""
                except Exception:
                    t = ""
                if t:
                    text += t + "\n"
        # If meaningful text found, return
        if len(text.strip()) > 50:
            return text
    except Exception as e:
        # Continue to OCR if any issue
        print(f"PyPDF2 extraction failed: {e}", file=sys.stderr)

    # Fallback to OCR for image-based PDFs
    try:
        images = pdf2image.convert_from_path(pdf_path)
        ocr_text = []
        for img in images:
            try:
                ocr_text.append(pytesseract.image_to_string(img))
            except Exception as e:
                print(f"OCR on page failed: {e}", file=sys.stderr)
        return "\n".join(ocr_text)
    except Exception as e:
        print(f"OCR extraction failed: {e}", file=sys.stderr)
        return ""


def normalize_text(text: str) -> str:
    """Normalize whitespace and common artifacts to improve parsing."""
    # Replace non-breaking spaces and normalize whitespace
    t = text.replace('\u00a0', ' ')
    t = re.sub(r"[\t\r]+", " ", t)
    t = re.sub(r"\s+", " ", t)
    return t


def detect_semester(text: str) -> str:
    """Detect semester from the entire text. Handles formats like:
    - Sixth Semester, 6th Semester, Semester: 6, Semester VI
    Returns numeric string or empty string if not found.
    """
    lt = text.lower()

    # Map words to numbers
    word_map = {
        "first": 1, "second": 2, "third": 3, "fourth": 4, "fifth": 5,
        "sixth": 6, "seventh": 7, "eighth": 8, "ninth": 9, "tenth": 10
    }
    for w, n in word_map.items():
        if re.search(rf"\b{w}\s+semester\b", lt):
            return str(n)

    # 6th, 7th etc
    m = re.search(r"\b([1-9]|1[0])(?:st|nd|rd|th)\s+semester\b", lt)
    if m:
        return m.group(1)

    # Semester: 6
    m = re.search(r"\bsemester\s*[:\-]?\s*([1-9]|1[0])\b", lt)
    if m:
        return m.group(1)

    # Semester VI roman
    def roman_to_int(s: str) -> int:
        s = s.upper()
        vals = {'I': 1, 'V': 5, 'X': 10}
        total, prev = 0, 0
        for ch in reversed(s):
            v = vals.get(ch, 0)
            if v < prev:
                total -= v
            else:
                total += v
                prev = v
        return total

    m = re.search(r"\bsemester\s*[:\-]?\s*([ivx]{1,4})\b", lt)
    if m:
        val = roman_to_int(m.group(1))
        if val:
            return str(val)

    return ""


def detect_academic_year(text: str) -> str:
    """Detect academic year like 2024-25, 2024/2025, 2024-2025."""
    # Prefer YYYY-YY
    m = re.search(r"\b(20\d{2})[-/](\d{2})\b", text)
    if m:
        return f"{m.group(1)}-{m.group(2)}"
    # Fallback YYYY-YYYY
    m = re.search(r"\b(20\d{2})[-/](20\d{2})\b", text)
    if m:
        return f"{m.group(1)}-{m.group(2)[-2:]}"
    return ""


def parse_subject_rows(raw_text: str):
    """Parse subject rows from text.
    Accepts patterns like:
      CS3210 Operating Systems 4 A 34
      CS3261 Operating Systems Laboratory 2 A 18
    Returns list of dicts with subject_code, subject_name, credit, letter_grade, total_grade_point.
    """
    subjects = []

    # Work line-by-line but also allow wrapped names: merge short lines with next
    lines = [l.strip() for l in raw_text.split('\n') if l.strip()]
    merged = []
    i = 0
    while i < len(lines):
        cur = lines[i]
        # If a line looks like it ends mid-word and next starts lowercase, merge
        if i + 1 < len(lines) and (cur.endswith('-') or len(cur) < 25):
            nxt = lines[i + 1]
            if nxt and nxt[0].islower():
                cur = (cur.rstrip('-') + nxt).strip()
                i += 1
        merged.append(cur)
        i += 1

    # Subject line regex
    subject_re = re.compile(
        r"\b([A-Z]{2,5}\d{3,4})\s+"           # subject code e.g., CS3210
        r"([A-Za-z0-9&.,/()\-\s]+?)\s+"       # subject name (non-greedy)
        r"(\d{1,2})\s+"                        # credit
        r"([OASABCDEF][+\-]?)\s+"              # letter grade (allow O/S and +/-)
        r"(\d{1,3})\b"                         # total grade point
    )

    for line in merged:
        for m in subject_re.finditer(line):
            subj = {
                "subject_code": m.group(1).strip(),
                "subject_name": re.sub(r"\s+", " ", m.group(2)).strip(),
                "credit": int(m.group(3)),
                "letter_grade": m.group(4).upper(),
                "total_grade_point": int(m.group(5)),
            }
            subjects.append(subj)

    # Deduplicate by (subject_code, subject_name)
    seen = set()
    unique = []
    for s in subjects:
        key = (s["subject_code"], s["subject_name"], s["credit"])
        if key not in seen:
            seen.add(key)
            unique.append(s)

    return unique


def parse_marks_data(text: str):
    """Parse extracted text to find marks information."""
    marks_data = {
        "subjects": [],
        "total_marks": 0,
        "obtained_marks": 0,
        "percentage": 0,
        "grade": "",
        "semester": "",
        "academic_year": ""
    }

    # Detect semester and academic year across entire text
    marks_data["semester"] = detect_semester(text)
    marks_data["academic_year"] = detect_academic_year(text)

    # Parse subject rows
    subjects = parse_subject_rows(text)
    marks_data["subjects"] = subjects

    # Aggregate stats if available
    if subjects:
        total_credits = sum(s.get("credit", 0) for s in subjects)
        total_gp = sum(s.get("total_grade_point", 0) for s in subjects)
        marks_data["total_credits"] = total_credits
        marks_data["total_grade_points"] = total_gp
        marks_data["cgpa"] = round(total_gp / total_credits, 2) if total_credits > 0 else 0

    return marks_data


def main():
    if len(sys.argv) != 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: python extract_marksheet_data.py <pdf_path>"
        }))
        sys.exit(1)

    pdf_path = sys.argv[1]

    if not os.path.exists(pdf_path):
        print(json.dumps({
            "success": False,
            "error": f"File not found: {pdf_path}"
        }))
        sys.exit(1)

    try:
        # Extract text from PDF
        text = extract_text_from_pdf(pdf_path)

        if not text.strip():
            print(json.dumps({
                "success": False,
                "error": "Could not extract text from PDF"
            }))
            sys.exit(1)

        # Parse marks data
        marks_data = parse_marks_data(text)

        # Return results
        print(json.dumps({
            "success": True,
            "data": marks_data,
            "extracted_text": text[:500] + "..." if len(text) > 500 else text
        }))

    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": f"Error processing PDF: {str(e)}"
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()
