import axios from 'axios';

const API_BASE = 'http://localhost:8000/api/v1';

const api = axios.create({
  baseURL: API_BASE,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const auth = {
  async login(username, password) {
    const res = await api.post('/auth/login', { username, password });
    localStorage.setItem('token', res.data.token);
    return res.data;
  },

  async getProfile() {
    const res = await api.get('/auth/me');
    return res.data;
  },

  async updateProfile(data) {
    const res = await api.put('/auth/me', data);
    return res.data;
  },

  async logout() {
    await api.post('/auth/logout');
    localStorage.removeItem('token');
  },
};

export const users = {
  async list() {
    const res = await api.get('/master/users');
    return res.data.data;
  },

  async get(id) {
    const res = await api.get(`/master/users/${id}`);
    return res.data;
  },

  async create(data) {
    const res = await api.post('/master/users', data);
    return res.data;
  },

  async update(id, data) {
    const res = await api.put(`/master/users/${id}`, data);
    return res.data;
  },

  async delete(id) {
    await api.delete(`/master/users/${id}`);
  },
};

export const satuan = {
  async list(search = '', perPage = 15) {
    const res = await api.get('/master/satuan', {
      params: { search, per_page: perPage }
    });
    return res.data;
  },

  async get(id) {
    const res = await api.get(`/master/satuan/${id}`);
    return res.data;
  },

  async create(data) {
    const res = await api.post('/master/satuan', data);
    return res.data;
  },

  async update(id, data) {
    const res = await api.put(`/master/satuan/${id}`, data);
    return res.data;
  },

  async delete(id) {
    await api.delete(`/master/satuan/${id}`);
  },
};
