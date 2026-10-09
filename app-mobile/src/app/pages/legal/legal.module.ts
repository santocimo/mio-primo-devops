import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { I18nModule } from '../../i18n/i18n.module';
import { LegalPageRoutingModule } from './legal-routing.module';
import { LegalPage } from './legal.page';

@NgModule({
  imports: [CommonModule, IonicModule, I18nModule, LegalPageRoutingModule],
  declarations: [LegalPage],
})
export class LegalPageModule {}
