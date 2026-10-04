import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { SubscribePageRoutingModule } from './subscribe-routing.module';
import { SubscribePage } from './subscribe.page';
import { I18nModule } from '../../i18n/i18n.module';

@NgModule({
  imports: [CommonModule, IonicModule, I18nModule, SubscribePageRoutingModule],
  declarations: [SubscribePage],
})
export class SubscribePageModule {}
