const BACKEND = 'https://hydronourish.infinityfreeapp.com/api';

export default {
  async fetch(request) {
    const url = new URL(request.url);

    if (request.method === 'GET' && url.pathname === '/get_data') {
      const res = await fetch(`${BACKEND}/get_data.php`);
      const data = await res.json();
      return Response.json(data, { status: res.status });
    }

    if (request.method === 'POST' && url.pathname === '/receive_data') {
      const body = await request.json();
      const res = await fetch(`${BACKEND}/receive_data.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      return Response.json(data, { status: res.status });
    }

    return Response.json({ error: 'Not found' }, { status: 404 });
  },
};
