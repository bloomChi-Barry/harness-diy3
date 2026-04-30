class Router {
  constructor() {
    this.routes = [];
  }

  register(method, path, handler) {
    this.routes.push({ method: method.toUpperCase(), path, handler });
  }

  resolve(method, path) {
    const m = method.toUpperCase();
    const route = this.routes.find((r) => r.method === m && r.path === path);
    return route ? route.handler : null;
  }
}

module.exports = Router;
