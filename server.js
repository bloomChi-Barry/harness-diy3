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
