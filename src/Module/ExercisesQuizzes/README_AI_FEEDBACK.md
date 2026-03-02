# Explication intelligente des erreurs (Hugging Face)

L'API utilise le **router** Hugging Face (`https://router.huggingface.co`) au format Chat Completions. L'ancienne URL `api-inference.huggingface.co` n'est plus supportée.

Variables d'environnement optionnelles (à mettre dans `.env.local`, ne pas committer le token) :

- `HUGGINGFACE_API_TOKEN` : token avec permission **Make calls to Inference Providers** (https://huggingface.co/settings/tokens). Si vide, le bouton "Générer explication IA" affichera un message d'indisponibilité.
- `HF_BASE_URL` : base URL du router (défaut : https://router.huggingface.co)
- `HF_MODEL` : modèle chat (défaut : meta-llama/Llama-3.2-3B-Instruct:fastest). Ajouter `:fastest` pour laisser le routeur choisir un fournisseur. Activer des fournisseurs sur https://huggingface.co/settings/inference-providers si besoin.
- `HF_TIMEOUT` : timeout en secondes (défaut : 30)
- `HF_MAX_FEEDBACK_PER_ATTEMPT` : limite d’appels automatiques par tentative (non utilisé pour l’instant ; génération uniquement au clic)
