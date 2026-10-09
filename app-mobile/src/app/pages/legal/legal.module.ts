import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { LegalPageRoutingModule } from './legal-routing.module';
import { LegalPage } from './legal.page';

@NgModule({
  imports: [CommonModule, IonicModule, LegalPageRoutingModule],
  declarations: [LegalPage],
})
export class LegalPageModule {}
