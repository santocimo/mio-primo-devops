import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { AdminSettingsPageRoutingModule } from './admin-settings-routing.module';
import { AdminSettingsPage } from './admin-settings.page';
import { I18nModule } from '../../i18n/i18n.module';
@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, I18nModule, AdminSettingsPageRoutingModule],
  declarations: [AdminSettingsPage],
})
export class AdminSettingsPageModule {}
