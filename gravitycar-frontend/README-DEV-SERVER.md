# React Development Server Management

This document explains how to manage the React development server for consistent port usage.

## 🎯 Configuration

The React development server is now configured to **always use port 3000** to match the Google OAuth redirect URI configuration.

### Vite Configuration
- **Port**: 3000 (fixed)
- **Strict Port**: Enabled (fails if port 3000 is not available instead of trying other ports)
- **Host**: Enabled for network access

## 🛠️ Available Scripts

### From the frontend directory (`gravitycar-frontend/`):

```bash
# Start development server (standard)
npm run dev

# Kill any processes on port 3000
npm run kill-port

# Restart development server (kills port 3000 first, then starts)
npm run restart
```

### Using shell scripts:

```bash
# From the frontend directory
./scripts/kill-port-3000.sh
./scripts/restart-dev-server.sh

# From the project root
./restart-frontend.sh
```

## 🚀 Recommended Workflow

### Starting the server:
```bash
cd gravitycar-frontend
npm run dev
```

### Restarting the server:
```bash
cd gravitycar-frontend
npm run restart
```

### Or from project root:
```bash
./restart-frontend.sh
```

## 🔧 Troubleshooting

### "Port 3000 is already in use"
If you get this error, run:
```bash
cd gravitycar-frontend
npm run kill-port
npm run dev
```

### Multiple React servers running
The scripts will automatically kill any existing processes on port 3000 before starting a new one.

## 🌐 URLs

- **Development**: http://localhost:3000/
- **Google OAuth Redirect**: http://localhost:3000/auth/google/callback (configured in .env)
- **Backend API**: http://localhost:8081/

## 📝 Benefits

1. **Consistent Port**: Always port 3000, no more random port selection
2. **Matches OAuth Config**: Aligns with Google OAuth redirect URI
3. **Easy Restart**: Simple scripts to handle port conflicts
4. **No Manual Port Management**: Automated cleanup and restart
