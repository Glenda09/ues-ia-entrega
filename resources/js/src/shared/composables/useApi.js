import { ref } from 'vue';
import api from '../services/http';

export function useApi() {
  const loading = ref(false);
  const error = ref(null);

  async function request(method, url, { params, data, headers } = {}) {
    loading.value = true; error.value = null;
    try {
      const res = await api({ method, url, params, data, headers });
      return res.data;
    } catch (e) {
      error.value = e;
      throw e;
    } finally {
      loading.value = false;
    }
  }
  return {
    loading, error,
    get: (u, o) => request('get', u, o),
    post: (u, o) => request('post', u, o),
    put: (u, o) => request('put', u, o),
    del: (u, o) => request('delete', u, o),
  };
}
