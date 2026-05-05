#!/bin/bash

echo "🚀 Starting Automatic Deployment..."

# 1. Kéo code mới nhất về (Nếu bạn dùng Git)
# git pull origin main

# 2. Build image và khởi động lại container (Không làm gián đoạn hệ thống quá lâu)
docker-compose up -d --build

# 3. Dọn dẹp các image cũ để không làm đầy ổ cứng
docker image prune -f

echo "✅ Deployment Successful! Your app is now live with the latest changes."
