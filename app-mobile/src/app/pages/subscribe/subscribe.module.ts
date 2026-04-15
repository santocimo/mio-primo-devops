import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { SubscribePageRoutingModule } from './subscribe-routing.module';
import { SubscribePage } from './subscribe.page';

@NgModule({
  imports: [CommonModule, IonicModule, SubscribePageRoutingModule],
  declarations: [SubscribePage],
})
export class SubscribePageModule {}
