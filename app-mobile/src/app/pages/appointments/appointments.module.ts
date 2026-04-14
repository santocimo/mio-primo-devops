import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { AppointmentsPageRoutingModule } from './appointments-routing.module';
import { AppointmentsPage } from './appointments.page';

@NgModule({
  imports: [CommonModule, IonicModule, AppointmentsPageRoutingModule],
  declarations: [AppointmentsPage],
})
export class AppointmentsPageModule {}
