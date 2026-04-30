const { sendJson, sendText } = require('./response');

function configureRoutes(router) {
  router.register('GET', '/', (req, res) => {
    sendText(res, 200, '<html><body><h1>Welcome to Native HTTP Router</h1></body></html>');
  });

  router.register('GET', '/api/hello', (req, res) => {
    sendJson(res, 200, { message: 'Hello, World!' });
  });

  router.register('GET', '/api/users', (req, res) => {
    sendJson(res, 200, [
      { id: 1, name: 'Alice', email: 'alice@example.com' },
      { id: 2, name: 'Bob', email: 'bob@example.com' },
      { id: 3, name: 'Charlie', email: 'charlie@example.com' },
    ]);
  });
}

module.exports = { configureRoutes };
