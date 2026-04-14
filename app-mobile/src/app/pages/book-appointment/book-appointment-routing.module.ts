import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BookAppointmentPage } from './book-appointment.page';

const routes: Routes = [
  {
    path: '',
    component: BookAppointmentPage,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BookAppointmentPageRoutingModule {}
