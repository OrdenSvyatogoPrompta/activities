import { ApplicationConfig, inject, provideAppInitializer, provideZoneChangeDetection } from '@angular/core';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { provideRouter } from '@angular/router';

import { authInterceptor } from './api/auth.interceptor';
import { routes } from './app.routes';
import { AuthService } from './services/auth.service';

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection(),
    provideHttpClient(withInterceptors([authInterceptor]), withFetch()),
    provideRouter(routes),
    // тихий refresh: восстанавливаем сессию до первой навигации (guard'ы видят актуальный user)
    provideAppInitializer(() => inject(AuthService).restoreSession()),
  ],
};
