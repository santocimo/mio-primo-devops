import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { LanguageService } from '../../i18n/language.service';
import { LEGAL_DOCUMENTS, LegalDocument } from './legal-content';

@Component({
  selector: 'app-legal',
  templateUrl: './legal.page.html',
  styleUrls: ['./legal.page.scss'],
})
export class LegalPage {
  private readonly key: string;

  constructor(route: ActivatedRoute, private language: LanguageService) {
    this.key = route.snapshot.paramMap.get('doc') ?? '';
  }

  get document(): LegalDocument {
    const docs = LEGAL_DOCUMENTS[this.language.currentLanguage];
    return docs[this.key] ?? docs['terms'];
  }
}
