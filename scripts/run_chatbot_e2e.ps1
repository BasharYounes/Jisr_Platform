$ErrorActionPreference = "Stop"

php artisan optimize:clear
php artisan test tests/Feature/Chatbot/ChatbotEndToEndTest.php
php artisan test --filter=Chatbot
