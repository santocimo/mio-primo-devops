import { Component, OnInit, OnDestroy, ChangeDetectionStrategy } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';
import { AdminAuditEntry } from '../../models/business.model';
import { formatDateTimeInTimeZone } from '../../utils/local-time';

@Component({
    selector: 'app-admin-settings',
    templateUrl: './admin-settings.page.html',
    styleUrls: ['./admin-settings.page.scss'],
    changeDetection: ChangeDetectionStrategy.Eager,
    standalone: false
})
export class AdminSettingsPage implements OnInit, OnDestroy {
  auditEntries: AdminAuditEntry[] = [];
  auditLoading = true;
  auditLoadError = false;
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    public language: LanguageService,
    public router: Router
  ) {}

  ngOnInit(): void {
    this.loadAuditLog();
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  loadAuditLog(): void {
    this.auditLoading = true;
    this.auditLoadError = false;
    this.apiService.getAdminAuditLogs().pipe(takeUntil(this.destroy$)).subscribe({
      next: entries => {
        this.auditEntries = entries;
        this.auditLoading = false;
      },
      error: () => {
        this.auditLoadError = true;
        this.auditLoading = false;
      },
    });
  }

  auditActionLabel(action: AdminAuditEntry['action']): string {
    return `adminSettings.auditAction.${action}`;
  }

  auditResourceLabel(resource: string): string {
    return `adminSettings.auditResource.${resource}`;
  }

  formatAuditTime(value: string): string {
    return formatDateTimeInTimeZone(value, 'Europe/Rome', this.language.currentLanguage, true);
  }
}
