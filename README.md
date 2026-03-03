# LinguaLearn – Language Learning Platform

## Overview

This project was developed as part of the PIDEV – 3rd Year Engineering Program at **Esprit School of Engineering** (Academic Year 2025–2026).

LinguaLearn is a full-stack web application designed to make language learning interactive and effective. It combines pedagogical content, AI-powered exercises, international test preparation, a community forum, and real-time features — all in one platform.

## Features

- 🎓 **Pedagogical Content** — Structured lessons and learning materials
- 🧠 **Exercises & Quizzes** — AI-generated exercises with an AI Coach (LLaMA 3.1)
- 📝 **International Tests** — IELTS/TOEFL-style preparation with AI evaluation (Gemini, AssemblyAI)
- 💬 **Community Forum** — Peer discussion and language exchange
- 🛠️ **Support System** — Ticket-based user support
- 👤 **User Management** — Registration, email verification, password reset, role-based access
- 🔐 **WebAuthn / Face Recognition** — Passwordless and biometric authentication
- 💳 **Stripe Payments** — Monthly & yearly subscription plans
- 🔔 **Real-time Notifications** — Powered by Pusher
- 📄 **PDF Export** — Generate documents with DomPDF
- 📊 **Audit Logging** — Full activity tracking with DamienHarper Auditor
- 🔄 **Workflow & State Machine** — Quiz attempt lifecycle management

## Tech Stack

### Frontend

- Twig 3.x (templating)
- Symfony UX Turbo + Stimulus (SPA-like interactions)
- Webpack Encore (asset bundling)
- HTML5 / CSS3 / JavaScript

### Backend

- PHP 8.2+
- Symfony 7.4
- Doctrine ORM 3.x + Migrations
- Symfony Security, Form, Validator, Serializer
- Symfony Messenger (async jobs via Doctrine transport)
- Symfony Workflow (state machines)
- Symfony Mailer + Gmail SMTP

### Database

- MySQL 8.0 (`lingualearn_db`)

### Third-party Integrations

| Service | Purpose |
|---|---|
| **Stripe** | Subscription payments (monthly & yearly) |
| **Pusher** | Real-time notifications |
| **Google Gemini API** | AI evaluation for listening, writing & speaking |
| **AssemblyAI** | Speech-to-text for speaking tests |
| **HuggingFace** (LLaMA 3.1 8B) | AI Coach & exercise generation |
| **HuggingFace** (BART-large-CNN) | Content summarization |
| **WebAuthn** | Passwordless authentication |
| **Face Recognition API** | Biometric login (local microservice) |
| **DomPDF** | PDF generation |
| **Mercure** | Server-sent events (real-time) |

## Architecture

LinguaLearn uses a **modular MVC architecture** within Symfony 7.4:

## Academic Context

Developed at **Esprit School of Engineering – Tunisia**
PIDEV – 3A | 2025–2026

## Getting Started

### Prerequisites

- PHP ≥ 8.2
- Composer
- Symfony CLI
- MySQL 8.0
- Node.js + npm (for assets)

### Installation

```bash
# Clone the repository
git clone https://github.com/HamdiBelhaj0013ESP/Esprit-PIDEV-3A33-2026-Lingualearn.git
cd Esprit-PIDEV-3A33-2026-Lingualearn

# Install PHP dependencies
composer install

# Install JS dependencies & build assets
npm install
npm run build

# Configure environment
cp .env .env.local
# Edit .env.local with your credentials (see below)
```

### Database Setup

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load  # optional: load sample data
```

### Running the App

```bash
symfony server:start
```

The app will be available at `https://127.0.0.1:8000`.

### Environment Variables

Key variables to configure in `.env.local`:

```env
# App
APP_ENV=dev
APP_SECRET=your_app_secret

# Database
DATABASE_URL="mysql://root:@127.0.0.1:3306/lingualearn_db?serverVersion=8.0.32&charset=utf8mb4"

# Mailer (Gmail)
MAILER_DSN="gmail+smtp://your_email%40gmail.com:app_password@default"
MAILER_FROM=your_email@gmail.com

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_MONTHLY=price_...
STRIPE_PRICE_YEARLY=price_...

# AI Services
GEMINI_API_KEY=your_gemini_key
ASSEMBLYAI_API_KEY=your_assemblyai_key
HUGGINGFACE_API_KEY=hf_...
HUGGINGFACE_API_TOKEN=hf_...
HUGGINGFACE_API_URL=https://router.huggingface.co
HUGGINGFACE_MODEL=meta-llama/Llama-3.1-8B-Instruct
HUGGINGFACE_SUMMARY_MODEL=facebook/bart-large-cnn

# Pusher (real-time)
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=eu

# Face Recognition microservice
FACE_API_URL=http://localhost:5001
```

## Acknowledgments

- [Esprit School of Engineering](https://esprit.tn) for academic guidance and support
- [Symfony](https://symfony.com) and its open-source ecosystem
- [Stripe](https://stripe.com), [Pusher](https://pusher.com), [Google Gemini](https://ai.google.dev), [HuggingFace](https://huggingface.co), [AssemblyAI](https://www.assemblyai.com) for their APIs
