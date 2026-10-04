import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';

import { GymPage } from './gym.page';
import { RouterModule, Routes } from '@angular/router';
import { I18nModule } from '../../i18n/i18n.module';

const routes: Routes = [{ path: '', component: GymPage }];

@NgModule({
  imports: [CommonModule, FormsModule, IonicModule, I18nModule, RouterModule.forChild(routes)],
  declarations: [GymPage],
})
export class GymPageModule {}
