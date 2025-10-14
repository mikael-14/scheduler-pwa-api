#need openssl installed and in PATH
#generate self-signed certificate for localhost
openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout docker/nginx/selfsigned.key -out docker/nginx/selfsigned.crt -subj "/CN=localhost" -config "D:\wamp64\bin\apache\apache2.4.54.2\conf\openssl.cnf"