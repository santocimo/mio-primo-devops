import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { BookAppointmentPageRoutingModule } from './book-appointment-routing.module';
import { BookAppointmentPage } from './book-appointment.page';

@NgModule({
  imports: [CommonModule, IonicModule, BookAppointmentPageRoutingModule],
  declarations: [BookAppointmentPage],
})
export class BookAppointmentPageModule {}
