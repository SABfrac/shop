FROM node:20

# Устанавливаем рабочую директорию внутри контейнера
WORKDIR /app

# Копируем package.json и package-lock.json
COPY package*.json ./

# Устанавливаем зависимости
RUN npm install

# Копируем остальной код (хотя он будет заменен через volume в docker-compose)
COPY . .
# Открываем порт для dev-сервера
EXPOSE 8080

CMD ["npm", "run", "dev"]