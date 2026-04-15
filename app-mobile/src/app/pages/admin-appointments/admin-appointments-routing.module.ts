import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminAppointmentsPage } from './admin-appointments.page';
const routes: Routes = [{ path: '', component: AdminAppointmentsPage }];
@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class AdminAppointmentsPageRoutingModule {}
