"""
ocr_service.py
A small persistent local HTTP service that loads PaddleOCR ONCE at
startup, then serves OCR requests quickly afterward. This avoids the
multi-minute model-reload cost of spinning up PaddleOCR fresh on every
single scan (which is what running ocr_paddle.py per-request was doing).

Run this once and leave it running in its own terminal window while
using the ScannerEncoding app:

    py -3.13 python\ocr_service.py

It listens on http://127.0.0.1:5001 and exposes:

    POST /ocr
    Body (JSON): {"image_path": "C:\\full\\path\\to\\image.jpg"}
    Response (JSON): {"text": "...", "confidence": 92.4, "words": [...]}

Only accepts connections from localhost — this is meant to be called
by PHP running on the same machine, never exposed to the network.
"""
import os
import json

os.environ["FLAGS_use_mkldnn"] = "0"

from flask import Flask, request, jsonify
from paddleocr import PaddleOCR

app = Flask(__name__)

print("Loading PaddleOCR models... this happens once, please wait.")


def build_ocr():
    attempts = [
        dict(use_textline_orientation=True, lang='en', enable_mkldnn=False),
        dict(use_textline_orientation=True, lang='en'),
        dict(lang='en'),
    ]
    last_err = None
    for kwargs in attempts:
        try:
            return PaddleOCR(**kwargs)
        except (TypeError, ValueError) as e:
            last_err = e
            continue
    raise last_err


ocr = build_ocr()
print("PaddleOCR models loaded. Service ready on http://127.0.0.1:5001")


def run_ocr_on_image(image_path):
    lines = []
    words = []

    try:
        results = ocr.predict(image_path)
        for res in results:
            data = res.json if hasattr(res, 'json') else res
            if isinstance(data, dict) and 'res' in data:
                data = data['res']
            texts = data.get('rec_texts', [])
            scores = data.get('rec_scores', [])
            for text, score in zip(texts, scores):
                if text.strip() == '':
                    continue
                lines.append(text)
                words.append({"text": text, "conf": round(float(score) * 100, 1)})
    except AttributeError:
        result = ocr.ocr(image_path, cls=True)
        for page in result:
            if not page:
                continue
            for line in page:
                text = line[1][0]
                score = line[1][1]
                if text.strip() == '':
                    continue
                lines.append(text)
                words.append({"text": text, "conf": round(float(score) * 100, 1)})

    full_text = "\n".join(lines)
    avg_conf = round(sum(w['conf'] for w in words) / len(words), 1) if words else None

    return {
        "text": full_text,
        "confidence": avg_conf,
        "words": words
    }


@app.route('/ocr', methods=['POST'])
def ocr_endpoint():
    data = request.get_json(silent=True) or {}
    image_path = data.get('image_path')

    if not image_path:
        return jsonify({"error": "image_path is required"}), 400
    if not os.path.isfile(image_path):
        return jsonify({"error": f"Image file not found: {image_path}"}), 400

    try:
        result = run_ocr_on_image(image_path)
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "ok"})


if __name__ == "__main__":
    # Only listen on localhost (127.0.0.1), not 0.0.0.0 — this must
    # never be reachable from outside this machine.
    app.run(host='127.0.0.1', port=5001, debug=False)