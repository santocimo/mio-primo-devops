import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { AdminGymsPageRoutingModule } from './admin-gyms-routing.module';
import { AdminGymsPage } from './admin-gyms.page';
import { I18nModule } from '../../i18n/i18n.module';
@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, I18nModule, AdminGymsPageRoutingModule],
  declarations: [AdminGymsPage],
})
export class AdminGymsPageModule {}
