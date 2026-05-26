import axios from 'axios';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

const FALLBACK_HOST = '192.168.1.5';

const getHostBase = () => {
  if (!__DEV__) return 'https://tu-api-produccion.com';
  if (Platform.OS === 'web') return 'http://localhost:8000';
  const debuggerHost = Constants.expoConfig?.hostUri?.split(':')[0];
  if (!debuggerHost || debuggerHost.includes('ngrok') || debuggerHost.includes('exp.direct')) {
    return `http://${FALLBACK_HOST}:8000`;
  }
  return `http://${debuggerHost}:8000`;
};

export const getStorageUrl = (path: string | null | undefined): string | null => {
  if (!path) return null;
  return `${getHostBase()}/storage/${path}`;
};

const getBaseUrl = () => {
  if (!__DEV__) return 'https://tu-api-produccion.com/api';
  if (Platform.OS === 'web') return 'http://localhost:8000/api';
  const debuggerHost = Constants.expoConfig?.hostUri?.split(':')[0];
  if (!debuggerHost || debuggerHost.includes('ngrok') || debuggerHost.includes('exp.direct')) {
    return `http://${FALLBACK_HOST}:8000/api`;
  }
  return `http://${debuggerHost}:8000/api`;
};

const api = axios.create({
  baseURL: getBaseUrl(),
  timeout: 8000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

api.interceptors.request.use(req => {
  console.log('[API] →', req.method?.toUpperCase(), req.baseURL + req.url);
  return req;
});

api.interceptors.response.use(
  res => {
    console.log('[API] ← OK', res.status);
    return res;
  },
  err => {
    console.log('[API] ← ERROR', err.message);
    console.log('[API]   status:', err.response?.status);
    console.log('[API]   data:', JSON.stringify(err.response?.data));
    return Promise.reject(err);
  }
);

/**
 * Use native fetch (not axios) for multipart/form-data requests.
 * Axios + manual Content-Type strips the boundary, making the server unable
 * to parse the file. fetch() lets React Native set the full
 * "multipart/form-data; boundary=..." header automatically.
 */
export const fetchMultipart = async (
  endpoint: string,
  data: FormData,
  token: string,
): Promise<{ data: any }> => {
  const url = `${getBaseUrl()}${endpoint}`;
  console.log('[FETCH] → POST', url);
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      // Do NOT set Content-Type — fetch sets it with the correct boundary
    },
    body: data,
  });
  const json = await res.json();
  console.log('[FETCH] ←', res.status, JSON.stringify(json).slice(0, 120));
  if (!res.ok) {
    console.log('[CREATE] response error:', JSON.stringify(json));
    console.log('[CREATE] response status:', res.status);
    const err: any = new Error(json?.message ?? 'Request failed');
    err.response = { status: res.status, data: json };
    throw err;
  }
  console.log('[CREATE] response success:', JSON.stringify(json));
  return { data: json };
};

export const authApi = {
  register: (name: string, email: string, password: string) =>
    api.post('/register', { name, email, password, password_confirmation: password }),

  login: (email: string, password: string) =>
    api.post('/login', { email, password }),

  logout: (token: string) =>
    api.post('/logout', {}, { headers: { Authorization: `Bearer ${token}` } }),
};

export const inviteApi = {
  findByCode: (code: string) =>
    api.get(`/challenges/code/${code}`),

  join: (code: string, token: string) =>
    api.post(`/challenges/join/${code}`, {}, {
      headers: { Authorization: `Bearer ${token}` },
    }),
};

export const workoutApi = {
  list: (token: string) =>
    api.get('/workouts', { headers: { Authorization: `Bearer ${token}` } }),

  create: (data: { title: string; duration_minutes: number; calories: number; icon_name: string; challenge_id?: number; reps?: number }, token: string) =>
    api.post('/workouts', data, { headers: { Authorization: `Bearer ${token}` } }),
};

export const userApi = {
  me: (token: string) =>
    api.get('/me', { headers: { Authorization: `Bearer ${token}` } }),

  stats: (token: string) =>
    api.get('/user/stats', { headers: { Authorization: `Bearer ${token}` } }),

  weekly: (token: string) =>
    api.get('/user/weekly', { headers: { Authorization: `Bearer ${token}` } }),

  myChallenges: (token: string) =>
    api.get('/challenges/mine', { headers: { Authorization: `Bearer ${token}` } }),

  updateProfile: (data: FormData, token: string) =>
    fetchMultipart('/user/profile', data, token),
};

export const challengeApi = {
  list: (token: string) =>
    api.get('/challenges', { headers: { Authorization: `Bearer ${token}` } }),
  create: (form: {
    name: string;
    coverImage: string | null;
    durationDays: number;
    startDate: Date;
    gymDaysPerWeek: number[];
    challengeMode: string;
    useLocation: boolean;
    meetingPoint: string;
    useCamera: boolean;
  }, token: string) => {
    const data = new FormData();
    data.append('name', form.name);
    data.append('duration_days', String(form.durationDays));
    data.append('start_date', form.startDate.toISOString().split('T')[0]);
    data.append('gym_days_per_week', JSON.stringify(form.gymDaysPerWeek));
    data.append('challenge_mode', form.challengeMode);
    data.append('use_location', form.useLocation ? '1' : '0');
    data.append('meeting_point', form.meetingPoint);
    data.append('use_camera', form.useCamera ? '1' : '0');

    // Debug: log full payload
    const debugPayload: Record<string, any> = {};
    data.forEach((value, key) => {
      if (typeof value === 'string') debugPayload[key] = value;
      else debugPayload[key] = `[File: ${(value as any).name}]`;
    });
    console.log('[CREATE] payload:', JSON.stringify(debugPayload));

    if (form.coverImage) {
      const fileName = form.coverImage.split('/').pop() ?? 'cover.jpg';
      const ext = fileName.split('.').pop() ?? 'jpg';
      data.append('cover_image', {
        uri: form.coverImage,
        name: fileName,
        type: `image/${ext === 'jpg' ? 'jpeg' : ext}`,
      } as any);
    }

    return fetchMultipart('/challenges', data, token);
  },

  leaderboard: (challengeId: number | string, period: 'semana' | 'mes' | 'año', token: string) =>
    api.get(`/challenges/${challengeId}/leaderboard?period=${period}`, {
      headers: { Authorization: `Bearer ${token}` },
    }),

  update: (id: number | string, data: { name: string }, token: string) =>
    api.put(`/challenges/${id}`, data, {
      headers: { Authorization: `Bearer ${token}` },
    }),

  delete: (id: number | string, token: string) =>
    api.delete(`/challenges/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    }),
};

export default api;
