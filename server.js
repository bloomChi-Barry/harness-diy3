const http = require('http');
const Router = require('./src/router');
const { sendJson } = require('./src/response');
const { configureRoutes } = require('./src/routes');

const router = new Router();
configureRoutes(router);

const PORT = process.env.PORT || 3000;

const server = http.createServer((req, res) => {
  const handler = router.resolve(req.method, req.url);

  if (handler) {
    handler(req, res);
  } else {
    sendJson(res, 404, { error: 'Not Found' });
  }
});

server.listen(PORT, () => {
  console.log(`Server listening on http://localhost:${PORT}`);
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.error(`Error: Port ${err.port} is already in use.`);
  } else {
    console.error(`Error: ${err.message}`);
  }
  process.exit(1);
});

function shutdown() {
  server.close(() => {
    process.exit(0);
  });
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
