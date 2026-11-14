import { Injectable, inject } from '@angular/core';
import { Observable, of } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { Club } from '../models/club.model';
import { RemoteList } from './remote-list';

/** Каталог приходит целиком и фильтруется на клиенте. */
@Injectable({ providedIn: 'root' })
export class ClubsService {
  private readonly api = inject(ApiClient);

  readonly list = new RemoteList(() => this.api.get<Club[]>('/clubs'), 'Не удалось загрузить каталог');

  getBySlug(slug: string): Observable<Club> {
    const cached = this.list.items().find(c => c.id === slug);
    return cached ? of(cached) : this.api.get<Club>(`/clubs/${encodeURIComponent(slug)}`);
  }
}
