import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminUsersPage } from './admin-users.page';
const routes: Routes = [{ path: '', component: AdminUsersPage }];
@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class AdminUsersPageRoutingModule {}
