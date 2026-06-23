<?php

return [
    'status' => [
        'active' => 'Ativo',
        'on_hold' => 'Em pausa',
        'canceled' => 'Cancelado',
        'completed' => 'Terminado',
    ],
    'additional_status' =>  [
        'unstarted' => 'Não iniciado',
        'ended' => 'Finalizado',
    ],
    'shout' => [
        'one_take' => 'O medicamento :medicine é toma única',
        'completed' => 'O medicamento :medicine já concluiu a prescrição',
        'on_hold' => 'O medicamento :medicine está em pausa até ser novamente retomado',
        'canceled' => 'O medicamento :medicine foi cancelado',
        'times_day'=> 'Vai levar :dosage de :medicine :total_times vezes ao dia. (Total :takes)',
        'every_days' => 'Vai levar :dosage de :medicine a cada :total_times dias. (Total :takes)',
        'repeat_hour' => 'cada :frequency horas',
        'repeat_day' => 'cada :frequency dias',
    ],
    'schedule' =>[
        'empty'  => 'Este medicamento não têm agendamento',
    ],
];
