import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { AdminGymsPageRoutingModule } from './admin-gyms-routing.module';
import { AdminGymsPage } from './admin-gyms.page';
@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, AdminGymsPageRoutingModule],
  declarations: [AdminGymsPage],
})
export class AdminGymsPageModule {}
