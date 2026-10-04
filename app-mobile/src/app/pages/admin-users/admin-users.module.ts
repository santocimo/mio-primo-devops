import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { AdminUsersPageRoutingModule } from './admin-users-routing.module';
import { AdminUsersPage } from './admin-users.page';
import { I18nModule } from '../../i18n/i18n.module';
@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, I18nModule, AdminUsersPageRoutingModule],
  declarations: [AdminUsersPage],
})
export class AdminUsersPageModule {}
