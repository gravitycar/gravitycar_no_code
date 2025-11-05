# Environment Configuration Required

## D&D RAG Chat API URLs

The `.env` file in `gravitycar-frontend/` needs to be updated with the following environment variables:

```bash
# D&D RAG Chat Server
VITE_DND_RAG_API_URL_LOCAL=http://localhost:5000
VITE_DND_RAG_API_URL_PRODUCTION=https://dndchat.gravitycar.com
```

**Note**: The `.env` file is gitignored (as it should be for security), so this change must be made manually on each environment where the application is deployed.

## Current Configuration

The application will auto-detect the environment based on `window.location.hostname`:
- **localhost** → Uses `VITE_DND_RAG_API_URL_LOCAL`
- **production** → Uses `VITE_DND_RAG_API_URL_PRODUCTION`

## Setup Instructions

1. Open `gravitycar-frontend/.env`
2. Add the two environment variables shown above
3. Save the file
4. Restart the React dev server if it's running

## Production Deployment

For production deployment, ensure the environment variables are set in your deployment configuration (e.g., Vercel environment variables, Docker compose, etc.).
