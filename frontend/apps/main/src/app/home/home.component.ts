import { ChangeDetectionStrategy, Component } from '@angular/core';

@Component({
  selector: 'act-home',
  template: `
    <div class="home">
      <h1>Activities</h1>
      <p>Добро пожаловать!</p>
    </div>
  `,
  styles: [`
    .home {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      gap: 16px;
    }

    h1 {
      font-size: 2.5rem;
      margin: 0;
    }

    p {
      font-size: 1.2rem;
      color: #666;
      margin: 0;
    }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomeComponent {}
