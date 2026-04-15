import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGymsPage } from './admin-gyms.page';
const routes: Routes = [{ path: '', component: AdminGymsPage }];
@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class AdminGymsPageRoutingModule {}
