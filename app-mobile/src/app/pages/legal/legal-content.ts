export interface LegalDocument {
  title: string;
  sections: { heading: string; paragraphs: string[] }[];
}

const IT: Record<string, LegalDocument> = {
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

const EN: Record<string, LegalDocument> = {
  terms: {
    title: 'Terms of service',
    sections: [
      {
        heading: 'Service',
        paragraphs: [
          'BusinessRegistry is an online service for managing clients, appointments and services of gyms, beauty centres and professional studios.',
          'By registering you declare that you have the legal capacity to act on behalf of the business you indicate.',
        ],
      },
      {
        heading: 'Free trial and subscription',
        paragraphs: [
          'Every new business has a 7-day free trial. After the trial, a monthly or yearly subscription is required to keep using the service.',
          'The subscription belongs to the business and is shared by all its operators. Only the billing contact can manage or cancel it.',
        ],
      },
      {
        heading: 'User responsibilities',
        paragraphs: [
          'You are responsible for keeping your credentials confidential and for the data of your clients that you enter in the service, including compliance with data protection law towards them.',
        ],
      },
      {
        heading: 'Changes and contacts',
        paragraphs: [
          'We may update these terms; significant changes will be announced in the app. For support, use the contacts shown in the app.',
        ],
      },
    ],
  },
  privacy: {
    title: 'Privacy policy',
    sections: [
      {
        heading: 'Data processed',
        paragraphs: [
          'Data of the business and its operators (first name, last name, tax code, email, username) and data of the business\'s clients entered by the user (personal details, appointments, services).',
          'Payment card data is collected directly by Stripe and never passes through or is stored on our servers.',
        ],
      },
      {
        heading: 'Purposes and legal basis',
        paragraphs: [
          'To provide the service and manage the subscription (performance of the contract), to comply with legal obligations and to keep the service secure.',
          'For the business\'s clients data, the business is the data controller and we act as data processor.',
        ],
      },
      {
        heading: 'Recipients',
        paragraphs: [
          'Payment and subscription data is exchanged with Stripe (and PayPal, when enabled) to manage recurring charges.',
        ],
      },
      {
        heading: 'Retention and rights',
        paragraphs: [
          'You may request access, rectification and deletion of your data. Account deletion is available from the Profile section of the app.',
        ],
      },
    ],
  },
  subscription: {
    title: 'Subscription and automatic renewal',
    sections: [
      {
        heading: 'Automatic renewal',
        paragraphs: [
          'The monthly or yearly subscription renews automatically at the end of each period, charging the same amount to the chosen payment method until it is cancelled.',
        ],
      },
      {
        heading: 'How to cancel',
        paragraphs: [
          'The billing contact can cancel at any time from the app. Cancelling stops future renewals and the service stays active until the end of the period already paid.',
        ],
      },
      {
        heading: 'Failed payments',
        paragraphs: [
          'If a charge fails, the payment provider may retry it. If payment is not completed, access may be suspended.',
        ],
      },
      {
        heading: 'Refunds',
        paragraphs: [
          'Periods already paid are non-refundable except where required by law. Prices are shown before payment and any change will be announced in advance.',
        ],
      },
    ],
  },
};

export const LEGAL_DOCUMENTS: Record<'it' | 'en', Record<string, LegalDocument>> = { it: IT, en: EN };
