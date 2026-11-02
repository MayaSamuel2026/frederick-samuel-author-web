<?php
declare(strict_types=1);
/** Offline, privacy-preserving DE/EN application style editor. No third-party text transfer. */
function career_letter_voice(array $job, string $lang='de', string $tone='natural'): string {
    $role=trim((string)($job['title']??''));
    $company=trim((string)($job['company']??''));
    $effect=(bool)preg_match('/effectiveness|excellence|effizienz|commercial strategy|commercial operations|vertriebssteuerung|sales.force.effectiveness/i',$role);
    $tone=in_array($tone,['natural','confident','concise'],true)?$tone:'natural';
    if ($lang==='en') {
        $starts=[
          'natural'=>"Dear Hiring Manager,\n\nI am interested in the {$role} role at {$company}. My background combines pharmaceutical sales leadership with the kind of structured commercial execution that makes a difference in the field.",
          'confident'=>"Dear Hiring Manager,\n\nThe {$role} position at {$company} is a strong match for my experience leading pharmaceutical sales teams and improving commercial performance across markets.",
          'concise'=>"Dear Hiring Manager,\n\nI would like to apply for the {$role} position at {$company}. I bring extensive experience in pharmaceutical sales leadership and commercial effectiveness."
        ];
        $middle=$effect?
          "At Danone Medical Nutrition I led commercial effectiveness and training, introducing KPI dashboards, territory analysis, segmentation and targeting. I have also worked on commercial effectiveness at Novartis. My focus has always been practical: giving teams useful insight and helping them turn it into better decisions.":
          "My recent work at Uriach / Pascoe involves leading OTC/Rx sales activity across Germany, Austria, the Czech Republic, Slovakia and the UK. Before that, at Vifor Pharma, I built and led a nationwide team developing new medical-specialty segments. These roles have taught me to keep sales plans clear, support people well and stay close to what actually happens in the market.";
        $end=$tone==='concise'?"I would welcome a conversation about the role.\n\nKind regards,\nFrederick Samuel":
          "I would be pleased to discuss where my experience could be useful to {$company}.\n\nKind regards,\nFrederick Samuel";
        return $starts[$tone]."\n\n".$middle."\n\n".$end;
    }
    $starts=[
      'natural'=>"Sehr geehrtes Recruiting-Team,\n\ndie Position {$role} bei {$company} spricht mich an. Ich bringe langjährige Erfahrung in der Führung von Pharmavertriebsteams mit und kenne sowohl die strategische als auch die operative Seite des Geschäfts.",
      'confident'=>"Sehr geehrtes Recruiting-Team,\n\nfür die Position {$role} bei {$company} bringe ich eine fundierte Kombination aus internationaler Vertriebsführung, Pharmamarktkenntnis und systematischer Vertriebssteuerung mit.",
      'concise'=>"Sehr geehrtes Recruiting-Team,\n\nich bewerbe mich auf die Position {$role} bei {$company}. Meine Schwerpunkte sind Pharmavertrieb, Teamführung und Commercial Effectiveness."
    ];
    $middle=$effect?
      "Bei Danone Medical Nutrition habe ich den Bereich Commercial Effectiveness und Training geleitet und unter anderem KPI-Dashboards, Gebietsanalysen sowie Segmentierung und Targeting aufgebaut. Bereits bei Novartis habe ich an der Weiterentwicklung der Vertriebssteuerung gearbeitet. Mir ist wichtig, dass aus Analysen konkrete Entscheidungen und bessere Abläufe entstehen.":
      "Bei Uriach / Pascoe verantworte ich Vertriebsaktivitäten in Deutschland, Österreich, Tschechien, der Slowakei und im Vereinigten Königreich. Zuvor habe ich bei Vifor Pharma ein bundesweites Vertriebsteam für neue Facharztsegmente aufgebaut und geführt. Meine Erfahrung zeigt: Gute Führung braucht klare Ziele, verlässliche Umsetzung und ein Gespür für die Menschen im Markt.";
    $end=$tone==='concise'?"Über ein Gespräch freue ich mich.\n\nMit freundlichen Grüßen\nFrederick Samuel":
      "Gerne würde ich mit Ihnen besprechen, wie ich meine Erfahrung bei {$company} einbringen kann.\n\nMit freundlichen Grüßen\nFrederick Samuel";
    return $starts[$tone]."\n\n".$middle."\n\n".$end;
}

function career_refine_language(string $draft, array $job, string $lang='de', string $tone='natural'): string {
    $draft=trim(str_replace(["\r\n","\r"],"\n",$draft));
    if ($draft==='') return career_letter_voice($job,$lang,$tone);
    $tone=in_array($tone,['natural','confident','concise'],true)?$tone:'natural';
    // Correct common stilted forms without altering names, dates, achievements, or numbers.
    $en=[
      'I am writing to express my interest in the position of'=>'I am applying for the position of',
      'The scope of this role strongly aligns with'=>'This role draws on',
      'My professional background combines'=>'My experience brings together',
      'I bring a pragmatic management style'=>'I take a practical approach to leading teams',
      'My responsibilities have included'=>'My work has included',
      'I would welcome the opportunity to discuss how my experience could contribute to'=>'I would be pleased to talk about where my experience could help',
      'Thank you for considering my application.'=>'Thank you for your time.',
      'actionable reporting'=>'reports that help people decide what to do next',
      'translate priorities into measurable execution'=>'turn priorities into practical action',
      'hands-on implementation'=>'day-to-day delivery',
      'The scope of this role'=>'The role',
      'Dear Hiring Team,'=>'Dear Hiring Manager,'
    ];
    $de=[
      'Ihre ausgeschriebene Position als'=>'Die Position',
      'hat mein Interesse geweckt'=>'spricht mich an',
      'Die Aufgaben passen sehr gut zu meiner Erfahrung'=>'Die Aufgabe knüpft an meine Erfahrung an',
      'Ein besonderer Schwerpunkt meiner Laufbahn liegt auf'=>'Ich habe mich besonders mit folgenden Themen beschäftigt:',
      'Ich stehe für klare Prioritäten'=>'Mir sind klare Prioritäten wichtig',
      'Über eine Einladung zum Austausch freue ich mich.'=>'Über ein persönliches Gespräch freue ich mich.',
      'Gerne erläutere ich Ihnen in einem persönlichen Gespräch'=>'Gerne bespreche ich mit Ihnen',
      'eine messbare Umsetzung im Markt'=>'die konsequente Umsetzung im Markt',
      'strategische Weiterentwicklung kommerzieller Organisationen'=>'Weiterentwicklung von Vertriebsorganisationen',
    ];
    $draft=str_replace(array_keys($lang==='en'?$en:$de),array_values($lang==='en'?$en:$de),$draft);
    // Light editing of an existing letter is safer than replacing its factual content.
    if($lang==='en') {
      $draft=preg_replace('/\bIn recent roles, my responsibilities have included\b/u','Recently, I have worked on',$draft);
      if($tone==='confident') $draft=str_replace('I would be pleased to talk about','I would welcome a conversation about',$draft);
      if($tone==='concise') $draft=preg_replace('/\bI would be pleased to talk about where my experience could help\b/u','I would welcome a conversation with',$draft);
    } else {
      $draft=preg_replace('/\bIn meinen jüngsten Funktionen war ich unter anderem für\b/u','Zuletzt habe ich',$draft);
      if($tone==='confident') $draft=str_replace('Mir sind klare Prioritäten wichtig','Ich setze klare Prioritäten',$draft);
      if($tone==='concise') $draft=str_replace('Über ein persönliches Gespräch freue ich mich.','Ich freue mich auf ein Gespräch.',$draft);
    }
    $draft=preg_replace('/[ \t]+\n/u',"\n",$draft);
    $draft=preg_replace('/\n{3,}/u',"\n\n",$draft);
    return trim((string)$draft);
}
