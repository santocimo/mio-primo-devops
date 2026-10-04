import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { PaywallPageRoutingModule } from './paywall-routing.module';
import { PaywallPage } from './paywall.page';
import { I18nModule } from '../../i18n/i18n.module';

@NgModule({
  imports: [CommonModule, IonicModule, I18nModule, PaywallPageRoutingModule],
  declarations: [PaywallPage],
})
export class PaywallPageModule {}
