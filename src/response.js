function sendJson(res, statusCode, data) {
  const body = JSON.stringify(data);
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Content-Length', Buffer.byteLength(body));
  res.writeHead(statusCode);
  res.end(Buffer.from(body));
}

function sendText(res, statusCode, text) {
  res.setHeader('Content-Type', 'text/plain; charset=utf-8');
  res.setHeader('Content-Length', Buffer.byteLength(text));
  res.writeHead(statusCode);
  res.end(Buffer.from(text));
}

module.exports = { sendJson, sendText };
