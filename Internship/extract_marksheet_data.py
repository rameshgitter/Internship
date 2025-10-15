#!/usr/bin/env python3
"""
Marksheet Data Extraction Script
This script extracts marks data from uploaded PDF marksheets using OCR
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

def extract_text_from_pdf(pdf_path):
    """Extract text from PDF using PyPDF2 first, then OCR if needed"""
    text = ""
    
    try:
        # Try PyPDF2 first (for text-based PDFs)
        with open(pdf_path, 'rb') as file:
            pdf_reader = PyPDF2.PdfReader(file)
            for page in pdf_reader.pages:
                text += page.extract_text() + "\n"
        
        # If we got meaningful text, return it
        if len(text.strip()) > 50:
            return text
            
    except Exception as e:
        print(f"PyPDF2 extraction failed: {e}", file=sys.stderr)
    
    try:
        # Fallback to OCR (for image-based PDFs)
        images = pdf2image.convert_from_path(pdf_path)
        ocr_text = ""
        
        for image in images:
            ocr_text += pytesseract.image_to_string(image) + "\n"
        
        return ocr_text
        
    except Exception as e:
        print(f"OCR extraction failed: {e}", file=sys.stderr)
        return ""

def parse_marks_data(text):
    """Parse extracted text to find marks information"""
    marks_data = {
        "subjects": [],
        "total_marks": 0,
        "obtained_marks": 0,
        "percentage": 0,
        "grade": "",
        "semester": "",
        "academic_year": ""
    }
    
    lines = text.split('\n')
    
    # Common patterns for marks extraction
    subject_patterns = [
        r'([A-Z]{2,4}\d{3})\s+([A-Za-z\s]+?)\s+(\d+)\s+(\d+)',  # CS101 Subject Name 80 100
        r'([A-Z]+\d+)\s+(.+?)\s+(\d+)/(\d+)',  # CS101 Subject Name 80/100
        r'(\w+)\s+(\d+)\s+(\d+)',  # Subject 80 100
    ]
    
    # Extract semester and academic year
    for line in lines:
        line = line.strip()
        
        # Look for semester information
        sem_match = re.search(r'semester\s*:?\s*(\d+)', line, re.IGNORECASE)
        if sem_match:
            marks_data["semester"] = sem_match.group(1)
        
        # Look for academic year
        year_match = re.search(r'(\d{4}[-/]\d{2,4})', line)
        if year_match:
            marks_data["academic_year"] = year_match.group(1)
        
        # Try to extract subject marks
        for pattern in subject_patterns:
            matches = re.findall(pattern, line)
            for match in matches:
                if len(match) >= 3:
                    subject_code = match[0] if len(match) > 3 else "UNKNOWN"
                    subject_name = match[1] if len(match) > 3 else match[0]
                    obtained = int(match[-2])
                    total = int(match[-1])
                    
                    marks_data["subjects"].append({
                        "subject_code": subject_code,
                        "subject_name": subject_name.strip(),
                        "obtained_marks": obtained,
                        "total_marks": total,
                        "percentage": round((obtained / total) * 100, 2) if total > 0 else 0
                    })
    
    # Calculate overall statistics
    if marks_data["subjects"]:
        total_obtained = sum(s["obtained_marks"] for s in marks_data["subjects"])
        total_maximum = sum(s["total_marks"] for s in marks_data["subjects"])
        
        marks_data["obtained_marks"] = total_obtained
        marks_data["total_marks"] = total_maximum
        marks_data["percentage"] = round((total_obtained / total_maximum) * 100, 2) if total_maximum > 0 else 0
        
        # Assign grade based on percentage
        percentage = marks_data["percentage"]
        if percentage >= 90:
            marks_data["grade"] = "A+"
        elif percentage >= 80:
            marks_data["grade"] = "A"
        elif percentage >= 70:
            marks_data["grade"] = "B"
        elif percentage >= 60:
            marks_data["grade"] = "C"
        elif percentage >= 50:
            marks_data["grade"] = "D"
        else:
            marks_data["grade"] = "F"
    
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
            "extracted_text": text[:500] + "..." if len(text) > 500 else text  # First 500 chars for debugging
        }))
        
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": f"Error processing PDF: {str(e)}"
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()