import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { BookAppointmentPageRoutingModule } from './book-appointment-routing.module';
import { BookAppointmentPage } from './book-appointment.page';
import { I18nModule } from '../../i18n/i18n.module';

@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, I18nModule, BookAppointmentPageRoutingModule],
  declarations: [BookAppointmentPage],
})
export class BookAppointmentPageModule {}
