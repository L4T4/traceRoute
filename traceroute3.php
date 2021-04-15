<?php

    define ("SOL_IP", 0);
    define ("IP_TTL", 2);    // Constantes do sistema operacional

    $dest_url = $argv[1];   // $argv é um array que retorna os argumentos passados na linha de comando
    $maximum_hops = 30;
    $port = 33434;  // Porta padrão usada por programas de trace route, pode ser qualquer outra

    // Retornar o IP da URL inserida
    $dest_addr = gethostbyname ($dest_url);
    print "Tracerouting to destination: $dest_addr\n";

    $ttl = 1;
    while ($ttl < $maximum_hops) {
        // Cria sockets ICMP e UDP
        $recv_socket = socket_create (AF_INET, SOCK_RAW, getprotobyname ('icmp'));
        $send_socket = socket_create (AF_INET, SOCK_DGRAM, getprotobyname ('udp'));

        // Define o TTL como o TTL atual do loop while
        socket_set_option ($send_socket, SOL_IP, IP_TTL, $ttl);

        // Associa o socket ICMP com um edereço IP padrão
        socket_bind ($recv_socket, 0, 0);

        $flag_connected = false;	// Usado para verificar se conectou em alguma das tentativas
        $success_counter = 0; 		// Variável usada para tirar a média dos tempos de resposta
        $roundtrip_time = 0;		// Soma o tempo de espera das 3 tentativas de cada hop para tirar a média

        for($x = 0;$x < 3;$x++){
        	// Salva o tempo atual em microsegundos para calcular o tempo de resposta
        	$t1 = microtime(true);

        	// Envia um pacote UDP de tamanho zero para o destino na porta predefinida
	        socket_sendto ($send_socket, "", 0, 0, $dest_addr, $port);

	        // Aguarda por um array através do soquete ICMP, caso não receba nada dá timeout em 5 segundos
	        $r = array ($recv_socket);
	        $w = $e = array ();
	        socket_select ($r, $w, $e, 2);

	        // Verifica se há algo para ler caso não haja, houve um timeout
	        if (count ($r)) {
	            // Recebe dados do socket e o endereço de destino de onde esses dados vieram
	            socket_recvfrom ($recv_socket, $buf, 512, 0, $recv_addr, $recv_port);

	            // Calcula o tempo de resposta
	            $roundtrip_time += ((microtime(true) - $t1) * 1000);

	            // Caso receba um endereço vazio, retorna um asterisco
	            if (empty ($recv_addr)) {
	                $recv_addr = "*";
	                $recv_name = "*";
	            } else {
	                // Caso contrário retorna o nome do endereço recebido através do IP
	                $recv_name = gethostbyaddr ($recv_addr);
	            }

	            $flag_connected = true;
	            $success_counter++;
	        }

        }
        if($flag_connected){
        	printf ("%3d   %-15s  %.3f ms  %s\n", $ttl, $recv_addr,  ($roundtrip_time/$success_counter), $recv_name);
        } else {
            // Caso não tenha lido nada nas 3 tentativas houve um timeout
            printf ("%3d   (timeout)\n", $ttl);
        }

        // Fecha os sockets
        socket_close ($recv_socket);
        socket_close ($send_socket);

        // Aumenta o TTL em 1 para a próxima passagem do loop
        $ttl++;

        // Quando atingir o destino encerra o loop
        if ($recv_addr == $dest_addr) break;
    }