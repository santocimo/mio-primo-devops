import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { AdminServicesPageRoutingModule } from './admin-services-routing.module';
import { AdminServicesPage } from './admin-services.page';
import { I18nModule } from '../../i18n/i18n.module';
@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, I18nModule, AdminServicesPageRoutingModule],
  declarations: [AdminServicesPage],
})
export class AdminServicesPageModule {}
