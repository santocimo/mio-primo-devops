import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { AdminAppointmentsPageRoutingModule } from './admin-appointments-routing.module';
import { AdminAppointmentsPage } from './admin-appointments.page';
@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, AdminAppointmentsPageRoutingModule],
  declarations: [AdminAppointmentsPage],
})
export class AdminAppointmentsPageModule {}
