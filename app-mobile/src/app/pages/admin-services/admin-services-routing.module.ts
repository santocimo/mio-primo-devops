import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminServicesPage } from './admin-services.page';
const routes: Routes = [{ path: '', component: AdminServicesPage }];
@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class AdminServicesPageRoutingModule {}
