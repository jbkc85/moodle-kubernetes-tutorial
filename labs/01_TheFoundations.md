The Foundations
===========

Kubernetes itself is enough to run Moodle, but by default Kubernetes does not come shipped with easy way of virtualhosting sites outside of it's integrations into GCE, AWS and other hosted solutions using their LoadBalancer technologies.  However in my experience, I have never been able to obtain the budget for deploying multiple LoadBalancers per service, thus had to 'roll my own' when it came to virtualhosting solutions or front-end load balancers and reverse proxies.  Therefore here in the Foundations tutorial, I will be going through the addition of a Traefik Load Balancer for your Kubernetes cluster.

> If you would rather use a ``type: LoadBalancer`` service, please feel free to skip to the next lab!

Traefik Load Balancer
-----------

As mentioned before, we will be using [Traefik](traefik.io).  Traefik itself is a light weight load balancer written in Go that can also act as an Ingress Controller to Kubernetes.  What I mean by Ingress, is that Traefik can read the configurations sent to Kubernetes under the ``ingress`` component and automatically configure itself to appropriately route traffic into your internal cluster.  This prevents us from having to have a LoadBalancer or external IP per service, and adds some additional cost savings onto the host (given we had no money to spend anyhow!).


To get traefik setup, you can just run the following command:

```sh

$ kubectl apply -f m8s/files/traefik/

```

This will apply a Kubernetes Ingress Provider as well as a WebUI for Traefik.  This is basically taken directly from the [Kubernetes Documentation](https://docs.traefik.io/user-guide/kubernetes/) over at Traefik, so I won't be going too into detail here about it.

* Traefik Ingress: [proxy/traefik.yaml](m8s/proxy/traefik.yaml)
* Traefik WebUI: [proxy/traefik-webui.yaml](m8s/proxy/traefik-webui.yaml)

To verify we have our proxy setup (taken directly from the documentation in Traefik mentioned earlier), simply run the following:

```sh

$ kubectl get pods --namespace=kube-system
NAME                                         READY     STATUS    RESTARTS   AGE
kube-addon-manager-minikube                  1/1       Running   3          29d
kubernetes-dashboard-fhz0w                   1/1       Running   3          29d
tiller-deploy-327544198-dgfaw                1/1       Running   3          29d
traefik-ingress-controller-678226159-q50aw   1/1       Running   0          11s

```
> notice the traefik-ingress-controller and you are good

```sh

$ curl -XGET $(minikube ip)
404 page not found

```
> we get a 404 because no ingress is currently configured for the minikube ip, and therefore nothing is routed.

*Note:* The reason we only create a service and ingress in the ``traefik-webui.yaml`` is because the ``traefik.yaml`` actually starts the WebUI on port 8080 - it just doesn't expose it outside of the internal network.
