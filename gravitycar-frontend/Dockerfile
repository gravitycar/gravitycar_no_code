FROM node:20-alpine

WORKDIR /app

# Copy package files for layer caching
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci || npm install

# Source code is mounted as a volume in dev, so no COPY needed.
EXPOSE 3100

CMD ["npm", "run", "dev"]
