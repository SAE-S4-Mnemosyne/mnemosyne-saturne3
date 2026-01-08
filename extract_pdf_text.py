
import sys
import os
import pypdf

def extract_text(pdf_path, output_file):
    output_file.write(f"\n\n--- START OF {pdf_path} ---\n\n")
    if not os.path.exists(pdf_path):
        output_file.write("File not found.\n")
        return

    try:
        reader = pypdf.PdfReader(pdf_path)
        for page in reader.pages:
            text = page.extract_text()
            if text:
                output_file.write(text + "\n")
    except Exception as e:
        output_file.write(f"Error reading PDF: {e}\n")
    output_file.write(f"\n\n--- END OF {pdf_path} ---\n\n")

pdf_dir = r"c:\Users\Bousm\Documents\cours\SAE\SAE BUT2-1\PDF"
output_path = r"c:\Users\Bousm\Documents\cours\SAE\SAE BUT2-1\pdf_content.txt"

with open(output_path, "w", encoding="utf-8") as f:
    extract_text(os.path.join(pdf_dir, "Bdd.pdf"), f)
    extract_text(os.path.join(pdf_dir, "Cahier des charges Mnémosyne V7.1.pdf"), f)
    extract_text(os.path.join(pdf_dir, "Mnemosyne (1).pdf"), f)

print("Done writing to pdf_content.txt")
