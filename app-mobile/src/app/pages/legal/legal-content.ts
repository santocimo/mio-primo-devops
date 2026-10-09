export interface LegalDocument {
  title: string;
  sections: { heading: string; paragraphs: string[] }[];
}

export const LEGAL_DOCUMENTS: Record<string, LegalDocument> = {
  terms: {
    title: 'Termini di servizio',
    sections: [
      {
        heading: 'Servizio',
        paragraphs: [
          'BusinessRegistry è un servizio online per la gestione di clienti, appuntamenti e servizi di palestre, centri estetici e studi professionali.',
          'Registrandoti dichiari di avere la capacità di agire per conto dell\'attività indicata.',
        ],
      },
      {
        heading: 'Prova gratuita e abbonamento',
        paragraphs: [
          'Ogni nuova attività dispone di una prova gratuita di 7 giorni. Al termine, per continuare a usare il servizio è necessario un abbonamento mensile o annuale.',
          'L\'abbonamento è associato all\'attività e condiviso da tutti gli operatori. Solo il referente di fatturazione può gestirlo o annullarlo.',
        ],
      },
      {
        heading: 'Responsabilità dell\'utente',
        paragraphs: [
          'Sei responsabile della riservatezza delle tue credenziali e dei dati dei tuoi clienti che inserisci nel servizio, compreso il rispetto della normativa sulla protezione dei dati nei loro confronti.',
        ],
      },
      {
        heading: 'Modifiche e contatti',
        paragraphs: [
          'Possiamo aggiornare questi termini; le modifiche rilevanti saranno comunicate nell\'app. Per assistenza usa i contatti indicati nell\'app.',
        ],
      },
    ],
  },
  privacy: {
    title: 'Informativa sulla privacy',
    sections: [
      {
        heading: 'Dati trattati',
        paragraphs: [
          'Dati dell\'attività e dei suoi operatori (nome, cognome, codice fiscale, email, username) e dati dei clienti dell\'attività inseriti dall\'utente (anagrafica, appuntamenti, servizi).',
          'I dati della carta di pagamento sono raccolti direttamente da Stripe e non transitano né sono conservati sui nostri server.',
        ],
      },
      {
        heading: 'Finalità e base giuridica',
        paragraphs: [
          'Erogare il servizio e gestire l\'abbonamento (esecuzione del contratto), adempiere a obblighi di legge e garantire la sicurezza del servizio.',
          'Per i dati dei clienti dell\'attività, l\'attività è titolare del trattamento e noi agiamo come responsabili del trattamento.',
        ],
      },
      {
        heading: 'Destinatari',
        paragraphs: [
          'I dati di pagamento e di abbonamento sono scambiati con Stripe (e PayPal, quando attivo) per gestire gli addebiti ricorrenti.',
        ],
      },
      {
        heading: 'Conservazione e diritti',
        paragraphs: [
          'Puoi chiedere accesso, rettifica e cancellazione dei dati. La cancellazione dell\'account è disponibile dalla sezione Profilo dell\'app.',
        ],
      },
    ],
  },
  subscription: {
    title: 'Informativa su abbonamento e rinnovo automatico',
    sections: [
      {
        heading: 'Rinnovo automatico',
        paragraphs: [
          'L\'abbonamento mensile o annuale si rinnova automaticamente alla scadenza di ogni periodo, addebitando lo stesso importo sul metodo di pagamento scelto, finché non viene annullato.',
        ],
      },
      {
        heading: 'Come annullare',
        paragraphs: [
          'Il referente di fatturazione può annullare in qualsiasi momento dall\'app. L\'annullamento ferma i rinnovi futuri e il servizio resta attivo fino alla fine del periodo già pagato.',
        ],
      },
      {
        heading: 'Pagamenti non riusciti',
        paragraphs: [
          'Se un addebito non va a buon fine, il provider di pagamento può ritentarlo. In caso di mancato pagamento l\'accesso può essere sospeso.',
        ],
      },
      {
        heading: 'Rimborsi',
        paragraphs: [
          'I periodi già pagati non sono rimborsabili salvo quanto previsto dalla legge. I prezzi sono mostrati prima del pagamento e ogni modifica sarà comunicata in anticipo.',
        ],
      },
    ],
  },
};
