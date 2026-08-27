FROM node:22.18-alpine

WORKDIR /app

COPY grid-ui/package.json grid-ui/package-lock.json ./
RUN npm ci --no-audit

COPY grid-ui /app

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
