import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { LEGAL_DOCUMENTS, LegalDocument } from './legal-content';

@Component({
  selector: 'app-legal',
  templateUrl: './legal.page.html',
  styleUrls: ['./legal.page.scss'],
})
export class LegalPage {
  readonly document: LegalDocument;

  constructor(route: ActivatedRoute) {
    const key = route.snapshot.paramMap.get('doc') ?? '';
    this.document = LEGAL_DOCUMENTS[key] ?? LEGAL_DOCUMENTS['terms'];
  }
}
