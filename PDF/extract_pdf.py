#!/usr/bin/env python3
"""
Script pour extraire le texte des fichiers PDF du dossier et les sauvegarder en .txt
"""
import os
from pathlib import Path

try:
    from pypdf import PdfReader
except ImportError:
    try:
        from PyPDF2 import PdfReader
    except ImportError:
        print("❌ Installation de pypdf...")
        os.system("pip install pypdf --quiet")
        from pypdf import PdfReader

def extract_pdf_to_txt(pdf_path: Path) -> str:
    """Extrait le texte d'un PDF"""
    try:
        reader = PdfReader(str(pdf_path))
        text = []
        for i, page in enumerate(reader.pages, 1):
            page_text = page.extract_text()
            if page_text:
                text.append(f"\n{'='*60}\n📄 PAGE {i}\n{'='*60}\n")
                text.append(page_text)
        return "\n".join(text)
    except Exception as e:
        return f"❌ Erreur lors de la lecture: {e}"

def main():
    # Dossier courant (PDF/)
    pdf_dir = Path(__file__).parent
    
    # Trouver tous les PDF
    pdf_files = list(pdf_dir.glob("*.pdf"))
    
    if not pdf_files:
        print("❌ Aucun fichier PDF trouvé dans le dossier.")
        return
    
    print(f"📚 {len(pdf_files)} fichier(s) PDF trouvé(s)\n")
    
    for pdf_file in pdf_files:
        print(f"📖 Extraction de: {pdf_file.name}...")
        
        # Extraire le texte
        text = extract_pdf_to_txt(pdf_file)
        
        # Sauvegarder en .txt
        txt_file = pdf_file.with_suffix(".txt")
        with open(txt_file, "w", encoding="utf-8") as f:
            f.write(f"# Contenu extrait de: {pdf_file.name}\n")
            f.write(f"# Date d'extraction: {__import__('datetime').datetime.now()}\n\n")
            f.write(text)
        
        print(f"   ✅ Sauvegardé: {txt_file.name}")
    
    print(f"\n✅ Terminé! Les fichiers .txt sont dans le dossier PDF/")

if __name__ == "__main__":
    main()
