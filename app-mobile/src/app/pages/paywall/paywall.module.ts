import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { PaywallPageRoutingModule } from './paywall-routing.module';
import { PaywallPage } from './paywall.page';

@NgModule({
  imports: [CommonModule, IonicModule, PaywallPageRoutingModule],
  declarations: [PaywallPage],
})
export class PaywallPageModule {}
