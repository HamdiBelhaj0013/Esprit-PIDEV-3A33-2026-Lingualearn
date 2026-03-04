"""
LinguaLearn — Face Recognition Microservice
Run: python app.py
Listens on http://localhost:5001
"""

import os
import base64
import io
import json
import logging

import face_recognition
import numpy as np
from flask import Flask, request, jsonify
from PIL import Image

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)

# Directory where admin face embeddings are stored as .npy files
# Each file is named after the admin's user ID: e.g. "42.npy"
EMBEDDINGS_DIR = os.environ.get("EMBEDDINGS_DIR", "./embeddings")
os.makedirs(EMBEDDINGS_DIR, exist_ok=True)

TOLERANCE = float(os.environ.get("FACE_TOLERANCE", "0.5"))  # lower = stricter


def decode_image(base64_string: str) -> np.ndarray:
    """Decode a base64 image string (with or without data URI prefix)."""
    if "," in base64_string:
        base64_string = base64_string.split(",", 1)[1]
    image_bytes = base64.b64decode(base64_string)
    image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
    return np.array(image)


def load_embedding(user_id: int) -> np.ndarray | None:
    path = os.path.join(EMBEDDINGS_DIR, f"{user_id}.npy")
    if not os.path.exists(path):
        return None
    return np.load(path)


def save_embedding(user_id: int, embedding: np.ndarray) -> None:
    path = os.path.join(EMBEDDINGS_DIR, f"{user_id}.npy")
    np.save(path, embedding)


# ── Routes ────────────────────────────────────────────────────────────────────

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/enroll", methods=["POST"])
def enroll():
    """
    Register an admin's face.
    Body: { "user_id": 42, "image": "<base64>" }
    Stores the 128-d face embedding on disk.
    """
    data = request.get_json(force=True)
    user_id = data.get("user_id")
    image_b64 = data.get("image")

    if not user_id or not image_b64:
        return jsonify({"error": "user_id and image are required"}), 400

    image_array = decode_image(image_b64)
    encodings = face_recognition.face_encodings(image_array)

    if not encodings:
        return jsonify({"error": "no_face_detected"}), 422

    if len(encodings) > 1:
        return jsonify({"error": "multiple_faces_detected"}), 422

    save_embedding(int(user_id), encodings[0])
    app.logger.info(f"Enrolled face for user_id={user_id}")
    return jsonify({"status": "enrolled", "user_id": user_id})


@app.route("/verify", methods=["POST"])
def verify():
    """
    Verify a live frame against a stored embedding.
    Body: { "user_id": 42, "image": "<base64>" }
    Returns: { "match": true/false, "distance": 0.38 }
    """
    data = request.get_json(force=True)
    user_id = data.get("user_id")
    image_b64 = data.get("image")

    if not user_id or not image_b64:
        return jsonify({"error": "user_id and image are required"}), 400

    stored_embedding = load_embedding(int(user_id))
    if stored_embedding is None:
        return jsonify({"error": "no_enrollment_found", "user_id": user_id}), 404

    image_array = decode_image(image_b64)
    live_encodings = face_recognition.face_encodings(image_array)

    if not live_encodings:
        return jsonify({"match": False, "reason": "no_face_detected"})

    distance = float(face_recognition.face_distance([stored_embedding], live_encodings[0])[0])
    match = distance <= TOLERANCE

    app.logger.info(f"Verify user_id={user_id} distance={distance:.3f} match={match}")
    return jsonify({"match": match, "distance": round(distance, 4)})


@app.route("/delete/<int:user_id>", methods=["DELETE"])
def delete_enrollment(user_id: int):
    """Remove a stored face embedding (e.g. when an admin is deleted)."""
    path = os.path.join(EMBEDDINGS_DIR, f"{user_id}.npy")
    if os.path.exists(path):
        os.remove(path)
        return jsonify({"status": "deleted", "user_id": user_id})
    return jsonify({"error": "not_found"}), 404


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=False)
