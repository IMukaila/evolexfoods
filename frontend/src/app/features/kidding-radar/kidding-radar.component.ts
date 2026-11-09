import { ChangeDetectionStrategy, Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../core/services/api.service';
import { GestationRadar, RadarBucketKey } from './kidding-radar.models';
import { buildRadarView, dueLabel, formatDate, sourceLabel, toIsoDay, trackPosition } from './kidding-radar.insights';

type LoadState = 'loading' | 'ready' | 'error';

/**
 * Expected Kidding / Gestation Horizon Radar (§39).
 *
 * One glance answers "what is due, and when": cumulative 7/30/60/90-day
 * horizon cards, a visual due-date track, and every pregnant dam bucketed
 * by how close she is — with calculated due dates flagged as such.
 */
@Component({
  selector: 'app-kidding-radar',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './kidding-radar.component.html',
  styleUrl: './kidding-radar.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class KiddingRadarComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly titleService = inject(Title);

  readonly state = signal<LoadState>('loading');
  readonly payload = signal<GestationRadar | null>(null);
  readonly errorMessage = signal('');
  readonly selectedBucket = signal<RadarBucketKey | 'all'>('all');

  readonly view = computed(() => {
    const payload = this.payload();
    return payload ? buildRadarView(payload, toIsoDay(new Date())) : null;
  });

  readonly visibleBuckets = computed(() => {
    const view = this.view();
    if (!view) {
      return [];
    }
    const selected = this.selectedBucket();
    return view.buckets.filter((bucket) => selected === 'all' || bucket.key === selected);
  });

  ngOnInit(): void {
    this.titleService.setTitle('Kidding Radar · Evolex Hub');
    this.load();
  }

  load(): void {
    this.state.set('loading');
    this.errorMessage.set('');
    this.api
      .get<GestationRadar>('/livestock/gestation-radar', { _refresh: Date.now() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (payload) => {
          this.payload.set(payload);
          this.state.set('ready');
        },
        error: (error: HttpErrorResponse) => {
          this.state.set('error');
          this.errorMessage.set(
            error.status === 401
              ? 'Your session has expired. Sign in again to reload the radar.'
              : 'We could not load the gestation radar. Check the API connection and try again.'
          );
        }
      });
  }

  selectBucket(key: RadarBucketKey | 'all'): void {
    this.selectedBucket.set(this.selectedBucket() === key ? 'all' : key);
  }

  trackPosition(entry: GestationRadar['entries'][number]): number {
    const view = this.view();
    return view ? trackPosition(entry, view) : 0;
  }

  dueLabel = dueLabel;
  formatDate = formatDate;
  sourceLabel = sourceLabel;
}
