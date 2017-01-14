
===========

----

----

**WARNING**

This documentation is purely for example.  Please take into consideration all resource restraints and deployment options (such as logging) before taking this tutorial into production.  I will be adding bits and pieces over time as I am more familiar with best practices, but also welcome any comments/suggestions as people walkthrough this tutorial!

**WARNING**

----

----

**Table of Contents**:

1. [Getting Started](#getting_started)
2. [Proxy Setup](traefik.md)
  * TLDR; deploy with copy/paste
3. [Postgres Setup](postgres.md)
  * TLDR; deploy with copy/paste
4. [Moodle Setup](moodle.md)
  * TLDR; deploy with copy/paste

----

M8s is a Kubernetes deployment tutorial for Moodle.  There is absolutely an intention to move this to a [Helm Chart](http://helm.sh) however deploying Moodle to Kubernetes was the priority.  In this example I will walk you through setting up an Ingress Proxy, Postgres and finally Moodle in your Kubernetes environment to deploy Moodle.

> Note that the Ingress Proxy used here, [Traefik](traefik.io), is interchangable with any other method you choose.

---

Getting Started
---------------

**Don't have Kubernetes?**: I suggest starting with [MiniKube](https://github.com/kubernetes/minikube) to get going.

First and foremost, to get the files mentioned in this tutorial please clone the [docker-moodle](https://github.com/jbkc85/docker-moodle) repository.  As stated before, I plan on moving Moodlenetes to a Helm Chart after I get the initial manifests created.

```sh
$ git clone https://github.com/jbkc85/docker-moodle
$ cd docker-moodle
```

Proxy Setup
-----------

As mentioned before, we will be using [Traefik](traefik.io).  To get traefik setup, you can just run the following command:

```sh
$ kubectl apply -f moodlenetes/proxy/
```

This will apply a Kubernetes Ingress Provider as well as a WebUI for Traefik.  This is basically taken directly from the [Kubernetes Documentation](https://docs.traefik.io/user-guide/kubernetes/) over at Traefik, so I won't be going too into detail here about it.

* Traefik Ingress: [proxy/traefik.yaml](moodlenetes/proxy/traefik.yaml)
* Traefik WebUI: [proxy/traefik-webui.yaml](moodlenetes/proxy/traefik-webui.yaml)

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

Postgres Setup
--------------

Postgres is another obvious interchangable part of the Moodlenetes setup.  However since the database of Moodle is essential, I will be going a bit more in detail on how to set it up.

### Persistent Volume

The first step is getting a persistent volume setup in Kubernetes.  This is important as if you don't create a persistent volume, the data from Postgres can be potentially lost.

$ cat moodlenetes/postgres/persistent-volume.yaml

```yaml
apiVersion: v1
kind: PersistentVolume
metadata:
  name: local-postgresql-pv
  labels:
    type: local
spec:
  capacity:
    storage: 1Gi
  accessModes:
    - ReadWriteOnce
  hostPath:
    path: /tmp/postgresql
```

----

### Secrets

Passwords in Kubernetes isn't that difficult if you pass them in through the environment.  However, this isn't secure nor a best practice.  Therefore instead of making an 'easy tutorial', we are going to go through the creation of secrets.  For this particular setup, we use four secrets: Root Password, Database, Username and Password.  To create them, we basically upload them to the API through the 'secrets' type from plain text files.

If you wish to create your own text files, simply use the following:

```sh
# create txt files if you wish
$ echo -n "your_value" > moodlenetes/postgres/postgres-rootpassword.txt
$ echo -n "your_value" > moodlenetes/postgres/postgres-database.txt
$ echo -n "your_value" > moodlenetes/postgres/postgres-user.txt
$ echo -n "your_value" > moodlenetes/postgres/postgres-password.txt
```

If you wish to use the default (everything is ``moodle``), just leave the files as they are.

Whichever you choose, its now time to upload these secrets.  Once again, to read more about Secrets [just visit the documentation site](https://kubernetes.io/docs/user-guide/secrets/).

```sh
$ kubectl create secret generic postgres-credentials --from-file=moodlenetes/postgres/postgres-rootpassword.txt --from-file=moodlenetes/postgres/postgres-database.txt --from-file=moodlenetes/postgres/postgres-user.txt --from-file=moodlenetes/postgres/postgres-password.txt
```

After the secret is created, you should be able to describe it based on the name given, in our case ``postgres-credentials``.

```sh
$ kubectl describe secret postgres-credentials
Name:   postgres-credentials
Namespace:  default
Labels:   <none>
Annotations:  <none>

Type: Opaque

Data
====
postgres-database.txt:    6 bytes
postgres-password.txt:    6 bytes
postgres-rootpassword.txt:  6 bytes
postgres-user.txt:    6 bytes
```

Now we have our secrets, and we can get onto the Deployment!

----

### Deployment

The deployment of Postgres in Kubernetes requires a few pieces to operate as we want it to.  Though they all are organized in the same YAML file, I will show each one in detail here.

$ cat moodlenetes/postgres/deployment.yaml

First, we have our a service.  The service allows for communication to the pods under the ``selector`` metadata.  To learn more about services in Kubernetes, simply [read the docs for services](https://kubernetes.io/docs/user-guide/services/).

> Please note that this port is exposed only internally to the pods in the namespace we created.

```yaml
apiVersion: v1
kind: Service
metadata:
  name: moodle-postgresql
  labels:
    app: moodle
spec:
  ports:
    - port: 5432
  selector:
    app: moodle
    tier: postgresql
  clusterIP: None
```

Next, we have to claim our Persistent Volume, which you created in the first step.  In some cases, a Persistent Volume can only have a certain amount of claims.  So, in this particular example we are making our claim!

Once again, to read more about [Persistent Volumes](https://kubernetes.io/docs/user-guide/persistent-volumes/), go to the Kubernetes Docs!

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: postgresql-claim
  labels:
    app: moodle
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 1Gi
```

Finally, we get to the deployment.

```yaml
apiVersion: extensions/v1beta1
kind: Deployment
metadata:
  name: moodle-postgresql
  labels:
    app: moodle
spec:
  strategy:
    type: Recreate
  template:
    metadata:
      labels:
        app: moodle
        tier: postgresql
    spec:
      containers:
      - image: postgres:9.5-alpine
        name: database
        env:
        - name: ROOT_PASSWORD
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-rootpassword.txt
        - name: DATABASE
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-database.txt
        - name: USER
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-username.txt
        - name: PASSWORD
          valueFrom:
            secretKeyRef:
              name: postgres-credentials
              key: postgres-password.txt
        ports:
        - containerPort: 5432
          name: postgresql
        volumeMounts:
        - name: postgresql-persistent-storage
          mountPath: /var/lib/postgresql/
      volumes:
      - name: postgresql-persistent-storage
        persistentVolumeClaim:
          claimName: postgresql-claim
```

#### TLDR;

```sh
$ kubectl create secret generic postgres-credentials --from-file=moodlenetes/postgres/postgres-rootpassword.txt --from-file=moodlenetes/postgres/postgres-database.txt --from-file=moodlenetes/postgres/postgres-user.txt --from-file=moodlenetes/postgres/postgres-password.txt
$ kubectl apply -f moodlenetes/postgres/

```

Ending Notes
-------------

There are many things I need to do to sure up this documentation, but I wanted to share it in case anyone else is looking into it and may have a better solution or deployment already written.

Rather than post comments here, I would highly encourage anyone interested to use Github so I don't have to worry about ReCaptcha or spam.

[Click here for Comments/Suggestions/Issues](https://github.com/jbkc85/moodle-kubernetes-tutorial/issues)
