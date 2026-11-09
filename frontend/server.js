const express = require('express');
const path = require('path');
const app = express();

const PORT = process.env.PORT || 4200;

// Serve Angular built files from dist/frontend/browser
const distPath = path.join(__dirname, 'dist', 'frontend', 'browser');

app.use(express.static(distPath));

// Handle Angular routing — send all requests to index.html
app.get('*', (req, res) => {
  res.sendFile(path.join(distPath, 'index.html'));
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Evolex Foods frontend running on port ${PORT}`);
});
