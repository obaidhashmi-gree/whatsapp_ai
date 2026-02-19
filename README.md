# WhatsApp AI Task Management System

A Laravel 12 application that uses WhatsApp Cloud API and Google Gemini to manage tasks via natural language.

## Features
- **Assign Tasks**: Managers can assign tasks to employees using natural language (e.g., "Assign John to fix the bug by tomorrow").
- **View Tasks**: Users can ask "Show my tasks" to see their pending assignments.
- **Complete Tasks**: Users can mark tasks as done (e.g., "Complete task 12345").
- **AI Powered**: Google Gemini 2.5 Flash understands user intent and extracts structured data.

## Prerequisites
- PHP 8.2+
- Composer
- MySQL
- Ngrok (for local development)
- Meta Developer Account (WhatsApp Cloud API)
- Google Cloud Account (Gemini API)

## Setup Instructions

1. **Clone & Install**
   ```bash
   git clone <repo_url>
   cd whatsapp-laravel
   composer install
   ```

2. **Environment Configuration**
   Copy `.env.example` to `.env` and configure:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=whatsapp_laravel
   ...
   WHATSAPP_TOKEN=your_meta_token
   WHATSAPP_PHONE_NUMBER_ID=your_phone_id
   GEMINI_API_KEY=your_gemini_key
   ```

3. **Database**
   Create a MySQL database named `whatsapp_laravel` and run migrations:
   ```bash
   php artisan migrate
   ```

4. **Serve Application**
   ```bash
   php artisan serve
   ```

5. **Expose to Internet (Ngrok)**
   To receive WhatsApp webhooks locally, use ngrok:
   ```bash
   ngrok http 8000
   ```
   Copy the HTTPS URL (e.g., `https://xxxx-xx-xx-xx.ngrok-free.app`).

6. **Configure Webhook**
   - Go to Meta App Dashboard > WhatsApp > Configuration.
   - Click **Edit** under Webhook.
   - **Callback URL**: `https://your-ngrok-url/api/webhook`
   - **Verify Token**: `laravel-whatsapp-webhook`
   - Verify and Save.
   - Manage Webhook fields: Subscribe to `messages`.

## Usage
- **Add Users**: Manually add users to the `users` table with `phone` (format: `919876543210`) and `role` (`manager` or `employee`).
- **Send Message**: Send "Hello" to your WhatsApp Business number.
- **Commands**:
  - *"Assign task to [Name] do [Task]"*
  - *"Show my tasks"*
  - *"Complete task [ID]"*

## Troubleshooting
- **Webhook verification failed?** Check if ngrok is running and the URL is correct. Ensure the Verify Token matches.
- **No reply?** Check `storage/logs/laravel.log`. Ensure API keys are correct.
