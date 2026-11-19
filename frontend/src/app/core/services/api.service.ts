import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';

@Injectable({ providedIn: 'root' })
export class ApiService {
  constructor(private http: HttpClient) {}

  get<T>(path: string, params?: Record<string, string | number | undefined>) {
    let httpParams = new HttpParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          httpParams = httpParams.set(key, String(value));
        }
      });
    }
    return this.http.get<T>(`/api/v1${path}`, { params: httpParams });
  }

  post<T>(path: string, body: unknown) {
    return this.http.post<T>(`/api/v1${path}`, body);
  }

  put<T>(path: string, body: unknown) {
    return this.http.put<T>(`/api/v1${path}`, body);
  }

  patch<T>(path: string, body: unknown) {
    return this.http.patch<T>(`/api/v1${path}`, body);
  }

  delete<T>(path: string) {
    return this.http.delete<T>(`/api/v1${path}`);
  }

  getBlob(path: string) {
    return this.http.get(`/api/v1${path}`, { responseType: 'blob' });
  }
}
