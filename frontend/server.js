const express = require('express');
const path = require('path');
const https = require('https');
const http = require('http');

const app = express();
const PORT = process.env.PORT || 4200;
const BACKEND_URL = process.env.BACKEND_URL || 'https://api.evolexfoods.com';

// Trust reverse proxy (Railway edge)
app.set('trust proxy', 1);

// Security & HTTPS enforcement middleware
app.use((req, res, next) => {
  if (req.headers['x-forwarded-proto'] === 'http') {
    return res.redirect(301, 'https://' + req.headers.host + req.url);
  }
  res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'SAMEORIGIN');
  next();
});

// Proxy /api requests to backend so frontend has zero CORS issues
app.use('/api', (req, res) => {
  try {
    const targetUrl = new URL(BACKEND_URL + '/api' + req.url);
    const client = targetUrl.protocol === 'https:' ? https : http;

    const headers = { ...req.headers, host: targetUrl.host };
    delete headers['content-length'];

    const proxyReq = client.request(
      targetUrl,
      {
        method: req.method,
        headers: headers,
      },
      (proxyRes) => {
        res.writeHead(proxyRes.statusCode, proxyRes.headers);
        proxyRes.pipe(res);
      }
    );

    proxyReq.on('error', (err) => {
      console.error('API proxy error:', err);
      res.status(502).json({ error: 'Backend unreachable', details: err.message });
    });

    req.pipe(proxyReq);
  } catch (err) {
    console.error('Proxy URL parsing error:', err);
    res.status(500).json({ error: 'Proxy error', details: err.message });
  }
});

// Serve Angular built files from dist/frontend/browser
const distPath = path.join(__dirname, 'dist', 'frontend', 'browser');
app.use(express.static(distPath));

// Handle Angular routing — send all requests to index.html
app.get('*', (req, res) => {
  res.sendFile(path.join(distPath, 'index.html'));
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Evolex Foods frontend running on port ${PORT}`);
  console.log(`Proxying /api to ${BACKEND_URL}`);
});
