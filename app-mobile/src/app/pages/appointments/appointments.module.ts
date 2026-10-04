import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { AppointmentsPageRoutingModule } from './appointments-routing.module';
import { AppointmentsPage } from './appointments.page';
import { I18nModule } from '../../i18n/i18n.module';

@NgModule({
  imports: [CommonModule, IonicModule, I18nModule, AppointmentsPageRoutingModule],
  declarations: [AppointmentsPage],
})
export class AppointmentsPageModule {}
