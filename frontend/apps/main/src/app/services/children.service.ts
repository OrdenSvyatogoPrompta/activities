import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { Child, CreateChildPayload, UpdateChildPayload } from '../models/user.model';
import { AuthService } from './auth.service';
import { RemoteList } from './remote-list';

@Injectable({ providedIn: 'root' })
export class ChildrenService {
  private readonly api = inject(ApiClient);

  readonly list = new RemoteList(() => this.api.get<Child[]>('/me/children'), 'Не удалось загрузить список детей');

  constructor() {
    const auth = inject(AuthService);
    this.list.resetUnless(auth.user);
  }

  create(payload: CreateChildPayload): Observable<Child> {
    return this.api.post<Child>('/me/children', payload).pipe(
      tap(child => this.list.items.update(list => [...list, child])),
    );
  }

  update(id: string, payload: UpdateChildPayload): Observable<Child> {
    return this.api.patch<Child>(`/me/children/${encodeURIComponent(id)}`, payload).pipe(
      tap(child => this.list.items.update(list => list.map(c => (c.id === child.id ? child : c)))),
    );
  }

  remove(id: string): Observable<void> {
    return this.api.delete<void>(`/me/children/${encodeURIComponent(id)}`).pipe(
      tap(() => this.list.items.update(list => list.filter(c => c.id !== id))),
    );
  }
}
